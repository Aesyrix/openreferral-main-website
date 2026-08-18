<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\LeadOutcome;
use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadUpdate;
use App\Models\RejectionReason;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeadWorkflow
{
    protected array $allowedTransitions = [
        'new' => ['qualified', 'archived'],
        'qualified' => ['assigned', 'archived'],
        'assigned' => ['accepted', 'rejected', 'unresponsive'],
        'accepted' => ['contacted', 'rejected'],
        'contacted' => ['appointment_set', 'showing', 'unresponsive', 'closed_lost'],
        'appointment_set' => ['showing', 'under_contract', 'closed_lost', 'unresponsive'],
        'showing' => ['under_contract', 'closed_lost', 'contacted'],
        'under_contract' => ['closed_won', 'closed_lost'],
        'rejected' => ['assigned', 'qualified'],
        'unresponsive' => ['assigned', 'replaced', 'archived'],
        'closed_lost' => ['replaced', 'archived'],
    ];

    public function validateTransition(Lead $lead, string $toStatus): void
    {
        $current = is_object($lead->status) ? $lead->status->value : (string)$lead->status;

        if (!isset($this->allowedTransitions[$current]) || !in_array($toStatus, $this->allowedTransitions[$current])) {
            throw ValidationException::withMessages([
                'status' => ["Illegal status transition from {$current} to {$toStatus}."]
            ]);
        }
    }

    public function acceptLead(Lead $lead, User $agent): Lead
    {
        if ($lead->assigned_agent_id !== $agent->id) {
            throw ValidationException::withMessages(['lead' => ['Lead is not assigned to this agent.']]);
        }

        $this->validateTransition($lead, 'accepted');

        $oldStatus = is_object($lead->status) ? $lead->status->value : (string)$lead->status;

        $lead->status = LeadStatus::ACCEPTED;
        $lead->accepted_at = now();
        $lead->last_touched_at = now();
        $lead->exclusivity_locked_at = now();

        // Decrement agent package credit if not already consumed
        if (!$lead->package_credit_consumed && $agent->agentProfile) {
            $profile = $agent->agentProfile;
            if ($profile->complimentary_remaining > 0) {
                $profile->decrement('complimentary_remaining');
            } else {
                $profile->increment('used_credits');
            }
            $lead->package_credit_consumed = true;
        }

        $lead->save();

        // Update active assignment
        Assignment::where('lead_id', $lead->id)
            ->where('agent_id', $agent->id)
            ->where('status', AssignmentStatus::PENDING)
            ->update([
                'status' => AssignmentStatus::ACCEPTED,
                'responded_at' => now(),
            ]);

        // Log Lead Update & Activity
        LeadUpdate::create([
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'from_status' => $oldStatus,
            'to_status' => 'accepted',
            'outcome' => LeadOutcome::CONNECTED->value,
            'note' => 'Agent accepted referral and locked exclusivity.',
            'created_at' => now(),
        ]);

        Activity::create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'user_id' => $agent->id,
            'type' => 'accepted',
            'old_status' => $oldStatus,
            'new_status' => 'accepted',
            'body' => "Agent {$agent->name} accepted the exclusive referral.",
        ]);

        AuditLog::create([
            'actor_id' => $agent->id,
            'action' => 'lead_accepted',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'accepted', 'agent_id' => $agent->id],
        ]);

        return $lead;
    }

    public function rejectLead(Lead $lead, User $agent, string $reasonSlug, ?string $note = null): Lead
    {
        if ($lead->assigned_agent_id !== $agent->id) {
            throw ValidationException::withMessages(['lead' => ['Lead is not assigned to this agent.']]);
        }

        $reason = RejectionReason::where('slug', $reasonSlug)->first();
        if ($reason && $reason->requires_note && empty($note)) {
            throw ValidationException::withMessages(['note' => ['A detailed note is required for this rejection reason.']]);
        }

        $oldStatus = is_object($lead->status) ? $lead->status->value : (string)$lead->status;
        $this->validateTransition($lead, 'rejected');

        $lead->status = LeadStatus::QUALIFIED; // returns to staff pool / needs reassignment
        $lead->assigned_agent_id = null;
        $lead->accept_by_at = null;
        $lead->rejected_count += 1;
        $lead->last_touched_at = now();
        $lead->save();

        Assignment::where('lead_id', $lead->id)
            ->where('agent_id', $agent->id)
            ->whereIn('status', [AssignmentStatus::PENDING, AssignmentStatus::ACCEPTED])
            ->update([
                'status' => AssignmentStatus::REJECTED,
                'reason' => $reasonSlug,
                'note' => $note,
                'responded_at' => now(),
            ]);

        LeadUpdate::create([
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'from_status' => $oldStatus,
            'to_status' => 'rejected',
            'note' => "Rejected: " . ($reason ? $reason->label : $reasonSlug) . ($note ? " - {$note}" : ''),
            'created_at' => now(),
        ]);

        Activity::create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'user_id' => $agent->id,
            'type' => 'rejected',
            'old_status' => $oldStatus,
            'new_status' => 'rejected',
            'body' => "Agent {$agent->name} rejected referral. Reason: " . ($reason ? $reason->label : $reasonSlug),
        ]);

        AuditLog::create([
            'actor_id' => $agent->id,
            'action' => 'lead_rejected',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'old_values' => ['status' => $oldStatus, 'assigned_agent_id' => $agent->id],
            'new_values' => ['status' => 'qualified', 'reason' => $reasonSlug],
        ]);

        return $lead;
    }

    public function updateProgress(Lead $lead, User $user, string $toStatus, string $outcome, string $note, ?string $nextActionAt = null, ?string $appointmentAt = null, ?string $expectedCloseAt = null): Lead
    {
        $this->validateTransition($lead, $toStatus);

        $oldStatus = is_object($lead->status) ? $lead->status->value : (string)$lead->status;

        $lead->status = LeadStatus::from($toStatus);
        $lead->last_touched_at = now();

        if ($nextActionAt) {
            $lead->next_action_at = Carbon::parse($nextActionAt);
        }
        if ($appointmentAt) {
            $lead->appointment_at = Carbon::parse($appointmentAt);
        }
        if ($expectedCloseAt) {
            $lead->expected_close_at = Carbon::parse($expectedCloseAt);
        }

        $lead->save();

        LeadUpdate::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'from_status' => $oldStatus,
            'to_status' => $toStatus,
            'outcome' => $outcome,
            'note' => $note,
            'next_action_at' => $lead->next_action_at,
            'appointment_at' => $lead->appointment_at,
            'expected_close_at' => $lead->expected_close_at,
            'created_at' => now(),
        ]);

        Activity::create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'user_id' => $user->id,
            'type' => 'status_changed',
            'old_status' => $oldStatus,
            'new_status' => $toStatus,
            'body' => "Progress update: {$toStatus} ({$outcome}) - {$note}",
        ]);

        return $lead;
    }

    public function closeWon(Lead $lead, User $user, int $salePriceCents, float $referralFeePct, ?string $closedAt = null): Lead
    {
        $oldStatus = is_object($lead->status) ? $lead->status->value : (string)$lead->status;
        $this->validateTransition($lead, 'closed_won');

        $referralFeeCents = (int) ($salePriceCents * ($referralFeePct / 100.0));

        $lead->status = LeadStatus::CLOSED_WON;
        $lead->sale_price_cents = $salePriceCents;
        $lead->referral_fee_pct = $referralFeePct;
        $lead->referral_fee_cents = $referralFeeCents;
        $lead->closed_at = $closedAt ? Carbon::parse($closedAt) : now();
        $lead->last_touched_at = now();
        $lead->save();

        LeadUpdate::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'from_status' => $oldStatus,
            'to_status' => 'closed_won',
            'outcome' => LeadOutcome::CLOSED->value,
            'note' => "Lead closed! Sale Price: $" . number_format($salePriceCents / 100, 2) . " | Referral Fee ({$referralFeePct}%): $" . number_format($referralFeeCents / 100, 2),
            'created_at' => now(),
        ]);

        Activity::create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'user_id' => $user->id,
            'type' => 'closed',
            'old_status' => $oldStatus,
            'new_status' => 'closed_won',
            'body' => "Closed Won! Fee earned: $" . number_format($referralFeeCents / 100, 2),
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'lead_closed_won',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => [
                'status' => 'closed_won',
                'sale_price_cents' => $salePriceCents,
                'referral_fee_cents' => $referralFeeCents,
            ],
        ]);

        return $lead;
    }
}
