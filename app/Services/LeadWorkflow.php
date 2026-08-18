<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\TransactionType;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFeedback;
use App\Models\LeadStatusHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The lead lifecycle from BACKEND_GUIDE §4:
 *   new → assign → accept (in_progress) | reject (rejected) → converted
 *
 * Every transition writes `lead_status_history`, fires the right notification
 * event, and logs to `activity_logs`.
 */
class LeadWorkflow
{
    public function __construct(private NotificationDispatcher $notifications) {}

    /**
     * Assign or reassign a lead to an agent. Resets the lead to `new` so the
     * agent is asked to accept/reject it, and fires event #3.
     */
    public function assign(Lead $lead, User $agent, ?User $actor = null): Lead
    {
        if (! $agent->isAgent()) {
            throw ValidationException::withMessages(['agent_id' => ['That user is not an agent.']]);
        }

        if (! $agent->isActive()) {
            throw ValidationException::withMessages(['agent_id' => ['Only approved, active agents can receive leads.']]);
        }

        if ($lead->status === LeadStatus::CONVERTED) {
            throw ValidationException::withMessages(['agent_id' => ['A converted lead cannot be reassigned.']]);
        }

        $previousAgentId = $lead->assigned_to;

        DB::transaction(function () use ($lead, $agent, $actor, $previousAgentId) {
            if ($previousAgentId && $previousAgentId !== $agent->id) {
                LeadAssignment::where('lead_id', $lead->id)
                    ->where('agent_id', $previousAgentId)
                    ->whereNull('unassigned_at')
                    ->update(['unassigned_at' => now()]);
            }

            $from = $lead->status;

            $lead->update([
                'assigned_to' => $agent->id,
                'assigned_by' => $actor?->id,
                'assigned_at' => now(),
                'responded_at' => null,
                'status' => LeadStatus::NEW,
            ]);

            LeadAssignment::create([
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
                'assigned_by' => $actor?->id,
                'assigned_at' => now(),
            ]);

            if ($from !== LeadStatus::NEW) {
                $this->recordHistory($lead, $from, LeadStatus::NEW, $actor);
            }
        });

        $this->notifications->leadAssigned($lead->refresh(), $agent, $actor);

        ActivityLogger::lead(
            $previousAgentId && $previousAgentId !== $agent->id ? 'lead.reassigned' : 'lead.assigned',
            $lead->id,
            ['agent_id' => $agent->id, 'previous_agent_id' => $previousAgentId],
            $actor
        );

        return $lead;
    }

    /** Take a lead off its agent without deleting it. */
    public function unassign(Lead $lead, ?User $actor = null): Lead
    {
        $previousAgentId = $lead->assigned_to;

        if (! $previousAgentId) {
            return $lead;
        }

        DB::transaction(function () use ($lead, $previousAgentId, $actor) {
            LeadAssignment::where('lead_id', $lead->id)
                ->where('agent_id', $previousAgentId)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            $from = $lead->status;

            $lead->update([
                'assigned_to' => null,
                'assigned_at' => null,
                'responded_at' => null,
                'status' => LeadStatus::NEW,
            ]);

            if ($from !== LeadStatus::NEW) {
                $this->recordHistory($lead, $from, LeadStatus::NEW, $actor);
            }
        });

        ActivityLogger::lead('lead.unassigned', $lead->id, ['previous_agent_id' => $previousAgentId], $actor);

        return $lead->refresh();
    }

    /**
     * The agent's accept/reject decision (events #4/#5/#6).
     *
     * @param  'accept'|'reject'  $decision
     */
    public function respond(
        Lead $lead,
        User $agent,
        string $decision,
        ?string $reason = null,
        ?string $comments = null,
    ): LeadFeedback {
        if ($lead->assigned_to !== $agent->id) {
            throw ValidationException::withMessages(['decision' => ['This lead is not assigned to you.']]);
        }

        if (in_array($lead->status, [LeadStatus::CONVERTED, LeadStatus::REJECTED], true)) {
            throw ValidationException::withMessages([
                'decision' => ['This lead has already been closed and cannot be responded to.'],
            ]);
        }

        if ($lead->status === LeadStatus::IN_PROGRESS && $decision === 'accept') {
            throw ValidationException::withMessages(['decision' => ['You have already accepted this lead.']]);
        }

        if ($decision === 'reject' && blank($reason)) {
            throw ValidationException::withMessages(['reason' => ['A rejection reason is required.']]);
        }

        $feedback = DB::transaction(function () use ($lead, $agent, $decision, $reason, $comments) {
            $from = $lead->status;
            $to = $decision === 'accept' ? LeadStatus::IN_PROGRESS : LeadStatus::REJECTED;

            $lead->update(['status' => $to, 'responded_at' => now()]);

            $feedback = LeadFeedback::create([
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
                'decision' => $decision,
                'reason' => $decision === 'reject' ? $reason : null,
                'comments' => $comments,
                'created_at' => now(),
            ]);

            $this->recordHistory($lead, $from, $to, $agent);

            if ($decision === 'accept') {
                $this->recordLeadFee($lead, $agent);
            }

            return $feedback;
        });

        $this->notifications->agentResponded($lead->refresh(), $feedback, $agent);

        ActivityLogger::lead("lead.{$decision}ed", $lead->id, [
            'agent_id' => $agent->id,
            'reason' => $feedback->reason,
            'comments' => $comments,
        ], $agent);

        return $feedback;
    }

    /** Staff/admin status change — most importantly marking a lead converted (event #7). */
    public function changeStatus(Lead $lead, LeadStatus $to, ?User $actor = null): Lead
    {
        $from = $lead->status;

        if ($from === $to) {
            return $lead;
        }

        if ($to !== LeadStatus::NEW && ! $lead->assigned_to) {
            throw ValidationException::withMessages([
                'status' => ['Assign the lead to an agent before moving it through the pipeline.'],
            ]);
        }

        DB::transaction(function () use ($lead, $from, $to, $actor) {
            $lead->update([
                'status' => $to,
                'converted_at' => $to === LeadStatus::CONVERTED ? now() : null,
            ]);

            $this->recordHistory($lead, $from, $to, $actor);
        });

        $agent = $lead->assignedAgent;

        if ($agent) {
            $to === LeadStatus::CONVERTED
                ? $this->notifications->leadConverted($lead, $agent, $actor)
                : $this->notifications->leadStatusChanged($lead, $agent, $to->label(), $actor);
        }

        ActivityLogger::lead('lead.status_changed', $lead->id, [
            'from' => $from?->value,
            'to' => $to->value,
        ], $actor);

        return $lead->refresh();
    }

    private function recordHistory(Lead $lead, ?LeadStatus $from, LeadStatus $to, ?User $actor): void
    {
        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $actor?->id,
            'created_at' => now(),
        ]);
    }

    /**
     * The per-lead fee becomes revenue when the agent accepts (BACKEND_GUIDE §8
     * — swap the trigger here if the business decides on conversion instead).
     */
    private function recordLeadFee(Lead $lead, User $agent): void
    {
        if ($lead->lead_fee_cents <= 0) {
            return;
        }

        $alreadyBilled = Transaction::where('lead_id', $lead->id)
            ->where('type', TransactionType::LEAD_FEE)
            ->whereIn('status', ['completed', 'pending'])
            ->exists();

        if ($alreadyBilled) {
            return;
        }

        Transaction::create([
            'agent_id' => $agent->id,
            'lead_id' => $lead->id,
            'type' => TransactionType::LEAD_FEE,
            'amount_cents' => $lead->lead_fee_cents,
            'currency' => 'USD',
            'status' => 'completed',
            'reference' => 'FEE-'.$lead->reference_id,
            'created_at' => now(),
        ]);
    }
}
