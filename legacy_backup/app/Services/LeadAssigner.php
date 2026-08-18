<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeadAssigner
{
    public function assign(Lead $lead, User $agent, User $assignedBy, ?string $note = null, ?int $slaHours = null): Lead
    {
        if ($agent->role->value !== 'agent') {
            throw ValidationException::withMessages(['agent_id' => ['Target user is not an agent.']]);
        }

        $profile = $agent->agentProfile;
        if (!$profile) {
            throw ValidationException::withMessages(['agent_id' => ['Agent profile missing.']]);
        }

        // Check active lock
        $existingLock = Assignment::where('lead_id', $lead->id)
            ->whereIn('status', [AssignmentStatus::PENDING, AssignmentStatus::ACCEPTED])
            ->first();

        if ($existingLock && $existingLock->agent_id !== $agent->id) {
            throw ValidationException::withMessages(['lead' => ['Lead is currently locked by another active assignment.']]);
        }

        $hours = $slaHours ?? (int) Setting::get('sla_hours', 4);
        $acceptByAt = now()->addHours($hours);

        $oldStatus = is_object($lead->status) ? $lead->status->value : (string)$lead->status;

        $lead->status = LeadStatus::ASSIGNED;
        $lead->assigned_agent_id = $agent->id;
        $lead->assigned_by_id = $assignedBy->id;
        $lead->assigned_at = now();
        $lead->accept_by_at = $acceptByAt;
        $lead->last_touched_at = now();
        $lead->save();

        Assignment::create([
            'lead_id' => $lead->id,
            'agent_id' => $agent->id,
            'assigned_by_id' => $assignedBy->id,
            'status' => AssignmentStatus::PENDING,
            'note' => $note ?? 'Exclusive referral assignment.',
            'assigned_at' => now(),
            'accept_by_at' => $acceptByAt,
            'sla_due_at' => $acceptByAt,
        ]);

        Activity::create([
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'user_id' => $assignedBy->id,
            'type' => 'assigned',
            'old_status' => $oldStatus,
            'new_status' => 'assigned',
            'body' => "Lead assigned to agent {$agent->name}. Accept window: {$hours} hours.",
        ]);

        AuditLog::create([
            'actor_id' => $assignedBy->id,
            'action' => 'lead_assigned',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'assigned', 'agent_id' => $agent->id, 'accept_by_at' => $acceptByAt->toIso8601String()],
        ]);

        return $lead;
    }

    public function reassign(Lead $lead, User $newAgent, User $reassignedBy, string $note, ?int $slaHours = null): Lead
    {
        if (empty($note)) {
            throw ValidationException::withMessages(['note' => ['A note is required when reassigning a lead.']]);
        }

        // Close old assignments as reassigned
        Assignment::where('lead_id', $lead->id)
            ->whereIn('status', [AssignmentStatus::PENDING, AssignmentStatus::ACCEPTED, AssignmentStatus::REJECTED, AssignmentStatus::EXPIRED])
            ->update([
                'status' => AssignmentStatus::REASSIGNED,
            ]);

        return $this->assign($lead, $newAgent, $reassignedBy, $note, $slaHours);
    }
}
