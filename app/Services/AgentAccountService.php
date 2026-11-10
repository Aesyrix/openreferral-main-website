<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The agent approval workflow from BACKEND_GUIDE §3.
 * pending → active | rejected → suspended → active, plus soft removal.
 */
class AgentAccountService
{
    public function __construct(private NotificationDispatcher $notifications) {}

    /** Create the agent row + profile + settings after OTP signup. */
    public function register(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password) {
            $agent = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => Role::AGENT,
                'status' => UserStatus::PENDING,
                'email_verified_at' => now(), // proven by the signup OTP
            ]);

            AgentProfile::create(['user_id' => $agent->id]);
            UserSetting::create(['user_id' => $agent->id]);

            return $agent;
        });
    }

    public function approve(User $agent, User $actor): User
    {
        $this->assertAgent($agent);

        if ($agent->status === UserStatus::ACTIVE) {
            return $agent;
        }

        DB::transaction(function () use ($agent, $actor) {
            $agent->update(['status' => UserStatus::ACTIVE]);

            AgentProfile::updateOrCreate(
                ['user_id' => $agent->id],
                ['approved_by' => $actor->id, 'approved_at' => now(), 'rejected_reason' => null]
            );
        });

        $this->notifications->agentApproved($agent->refresh(), $actor);

        ActivityLogger::user('agent.approved', $agent->id, [], $actor);

        return $agent;
    }

    public function reject(User $agent, User $actor, ?string $reason = null): User
    {
        $this->assertAgent($agent);

        DB::transaction(function () use ($agent, $actor, $reason) {
            $agent->update(['status' => UserStatus::REJECTED]);
            $agent->tokens()->delete();
            $agent->devices()->delete();

            AgentProfile::updateOrCreate(
                ['user_id' => $agent->id],
                ['rejected_reason' => $reason, 'approved_by' => null, 'approved_at' => null]
            );

            $this->releaseOpenLeads($agent, $actor);
        });

        $this->notifications->agentRejected($agent->refresh(), $reason, $actor);

        ActivityLogger::user('agent.rejected', $agent->id, ['reason' => $reason], $actor);

        return $agent;
    }

    public function suspend(User $agent, User $actor, ?string $reason = null): User
    {
        $this->assertAgent($agent);

        DB::transaction(function () use ($agent, $actor) {
            $agent->update(['status' => UserStatus::SUSPENDED]);
            $agent->tokens()->delete();
            $agent->devices()->delete();

            $this->releaseOpenLeads($agent, $actor);
        });

        $this->notifications->agentAccountStatusChanged($agent->refresh(), 'suspended', $actor, $reason);

        ActivityLogger::user('agent.suspended', $agent->id, ['reason' => $reason], $actor);

        return $agent;
    }

    public function restore(User $agent, User $actor): User
    {
        $this->assertAgent($agent);

        $agent->update(['status' => UserStatus::ACTIVE]);

        AgentProfile::updateOrCreate(
            ['user_id' => $agent->id],
            ['approved_by' => $actor->id, 'approved_at' => now(), 'rejected_reason' => null]
        );

        $this->notifications->agentAccountStatusChanged($agent->refresh(), 'restored', $actor);

        ActivityLogger::user('agent.restored', $agent->id, [], $actor);

        return $agent;
    }

    /** Soft-delete: revoke access, release their open leads, keep history. */
    /**
     * Permanently delete the agent. The farewell email goes out first, while
     * the account still exists to send to.
     *
     * @return array the impact snapshot from UserEraser
     */
    public function remove(User $agent, User $actor): array
    {
        $this->assertAgent($agent);

        $this->notifications->agentAccountStatusChanged($agent, 'removed', $actor);

        return app(UserEraser::class)->erase($agent, $actor);
    }

    /**
     * Leads the agent had not converted go back to the unassigned pool so staff
     * can re-route them; converted/rejected history stays attached.
     */
    private function releaseOpenLeads(User $agent, ?User $actor): void
    {
        $open = Lead::where('assigned_to', $agent->id)
            ->whereIn('status', [LeadStatus::NEW, LeadStatus::IN_PROGRESS])
            ->get();

        foreach ($open as $lead) {
            app(LeadWorkflow::class)->unassign($lead, $actor);
        }
    }

    private function assertAgent(User $user): void
    {
        if (! $user->isAgent()) {
            throw ValidationException::withMessages(['user' => ['That account is not an agent.']]);
        }
    }
}
