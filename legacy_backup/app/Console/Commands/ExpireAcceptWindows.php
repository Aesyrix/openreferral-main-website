<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Lead;
use Illuminate\Console\Command;

class ExpireAcceptWindows extends Command
{
    protected $signature = 'leads:expire-accept-windows';
    protected $description = 'Expire pending lead assignments that passed SLA accept window';

    public function handle()
    {
        $expiredAssignments = Assignment::where('status', AssignmentStatus::PENDING)
            ->where('accept_by_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredAssignments as $assignment) {
            $assignment->update(['status' => AssignmentStatus::EXPIRED]);

            $lead = $assignment->lead;
            if ($lead && $lead->status->value === 'assigned') {
                $lead->update([
                    'status' => LeadStatus::UNRESPONSIVE,
                    'assigned_agent_id' => null,
                ]);

                Activity::create([
                    'subject_type' => Lead::class,
                    'subject_id' => $lead->id,
                    'type' => 'sla_breached',
                    'old_status' => 'assigned',
                    'new_status' => 'unresponsive',
                    'body' => "SLA Breach: Agent {$assignment->agent->name} failed to respond within accept window. Lead marked unresponsive.",
                ]);
            }
            $count++;
        }

        $this->info("Expired {$count} pending assignments exceeding SLA.");
    }
}
