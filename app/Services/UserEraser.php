<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFeedback;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Permanent user deletion — the row really leaves the `users` table.
 *
 * Everything personal goes with it (profile, service areas, settings, devices,
 * API tokens, their own notification inbox). Business records stay, with the
 * link to the person cleared:
 *
 *   - leads          → unassigned, otherwise intact
 *   - transactions   → kept, so revenue totals do not change retroactively
 *   - lead_feedback  → kept, so rejection analytics keep their history
 *   - status history → kept, attributed to nobody
 *   - activity_logs  → kept, so the audit trail survives the deletion
 *
 * This is irreversible: unlike suspension there is no soft-deleted row to
 * restore, so callers must confirm first.
 */
class UserEraser
{
    public function __construct(private LeadWorkflow $workflow) {}

    /**
     * What deleting this user would do, for the confirmation screen.
     *
     * @return array{leads_unassigned:int,leads_total:int,feedback:int,transactions:int,revenue_cents:int,notifications:int,devices:int,created_leads:int}
     */
    public function impact(User $user): array
    {
        return [
            'leads_total' => Lead::where('assigned_to', $user->id)->count(),
            'leads_unassigned' => Lead::where('assigned_to', $user->id)
                ->whereIn('status', [LeadStatus::NEW, LeadStatus::IN_PROGRESS])
                ->count(),
            'feedback' => LeadFeedback::where('agent_id', $user->id)->count(),
            'transactions' => Transaction::where('agent_id', $user->id)->count(),
            'revenue_cents' => (int) Transaction::where('agent_id', $user->id)
                ->where('status', 'completed')
                ->sum('amount_cents'),
            'notifications' => Notification::where('user_id', $user->id)->count(),
            'devices' => $user->devices()->count(),
            'created_leads' => Lead::where('created_by', $user->id)->count(),
        ];
    }

    /**
     * Erase the user for good.
     *
     * @return array the impact snapshot, taken before the deletion
     */
    public function erase(User $user, User $actor): array
    {
        $impact = $this->impact($user);

        $label = $user->name.' <'.$user->email.'>';
        $role = $user->role?->value;
        $id = $user->id;

        DB::transaction(function () use ($user, $actor) {
            // Revoke access first so nothing can act mid-deletion.
            $user->tokens()->delete();
            $user->devices()->delete();

            // Hand back anything they were still working on. The FK would null
            // `assigned_to` anyway, but going through the workflow writes the
            // history rows and keeps the lead's status honest.
            if ($user->isAgent()) {
                $open = Lead::where('assigned_to', $user->id)
                    ->whereIn('status', [LeadStatus::NEW, LeadStatus::IN_PROGRESS])
                    ->get();

                foreach ($open as $lead) {
                    $this->workflow->unassign($lead, $actor);
                }
            }

            // Detach the business records explicitly rather than relying on the
            // FK, so the intent is visible here and not just in a migration.
            Transaction::where('agent_id', $user->id)->update(['agent_id' => null]);
            LeadFeedback::where('agent_id', $user->id)->update(['agent_id' => null]);
            LeadAssignment::where('agent_id', $user->id)->update(['agent_id' => null]);

            // Their own inbox is personal data and goes with them; notifications
            // *about* them keep existing with actor_id nulled by the FK.
            Notification::where('user_id', $user->id)->delete();

            // Past the soft-delete layer: this removes the row from `users`.
            $user->forceDelete();
        });

        // Logged after the fact — the actor is not the deleted user, so this
        // row survives with its own actor_id intact.
        ActivityLogger::log('user.deleted_permanently', $actor, 'user', $id, [
            'user' => $label,
            'role' => $role,
            'impact' => $impact,
        ]);

        return $impact;
    }
}
