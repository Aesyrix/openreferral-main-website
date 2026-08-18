<?php

namespace Tests\Feature\Dashboard;

use App\Enums\LeadStatus;
use App\Enums\UserStatus;
use App\Mail\AgentAccountStatusMail;
use App\Mail\AgentApprovedMail;
use App\Mail\AgentRejectedMail;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AgentApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_approve_a_pending_agent(): void
    {
        Mail::fake();

        $staff = User::factory()->staff()->create();
        $agent = User::factory()->pending()->create();

        $this->actingAs($staff)
            ->post("/admin/agents/{$agent->id}/approve")
            ->assertRedirect();

        $agent->refresh();
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertSame($staff->id, $agent->agentProfile->approved_by);
        $this->assertNotNull($agent->agentProfile->approved_at);

        // Event #2 — in-app + email to the agent.
        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'type' => 'system']);
        Mail::assertSent(AgentApprovedMail::class, 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'agent.approved', 'subject_id' => $agent->id]);
    }

    public function test_rejecting_an_agent_stores_the_reason_and_revokes_access(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->pending()->create();
        $agent->createToken('mobile_agent');
        $agent->devices()->create(['token' => 'device-1', 'platform' => 'android']);

        $this->actingAs($admin)
            ->post("/admin/agents/{$agent->id}/reject", ['reason' => 'License could not be verified'])
            ->assertRedirect();

        $agent->refresh();
        $this->assertSame(UserStatus::REJECTED, $agent->status);
        $this->assertSame('License could not be verified', $agent->agentProfile->rejected_reason);
        $this->assertSame(0, $agent->tokens()->count());
        $this->assertSame(0, $agent->devices()->count());

        Mail::assertSent(AgentRejectedMail::class, 1);
    }

    public function test_suspending_an_agent_releases_their_open_leads(): void
    {
        Mail::fake();

        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->create();

        $newLead = Lead::factory()->assignedTo($agent->id, $staff->id)->create();
        $inProgress = Lead::factory()->assignedTo($agent->id, $staff->id)->create(['status' => LeadStatus::IN_PROGRESS]);
        $converted = Lead::factory()->assignedTo($agent->id, $staff->id)->create(['status' => LeadStatus::CONVERTED]);

        $this->actingAs($staff)->post("/admin/agents/{$agent->id}/suspend", ['reason' => 'Client complaint'])
            ->assertRedirect();

        $this->assertSame(UserStatus::SUSPENDED, $agent->fresh()->status);
        $this->assertNull($newLead->fresh()->assigned_to);
        $this->assertNull($inProgress->fresh()->assigned_to);
        $this->assertSame(LeadStatus::NEW, $inProgress->fresh()->status);
        $this->assertSame($agent->id, $converted->fresh()->assigned_to, 'Converted history stays attached.');

        Mail::assertSent(AgentAccountStatusMail::class, 1);
    }

    public function test_a_suspended_agent_can_be_reactivated(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->suspended()->create();

        $this->actingAs($admin)->post("/admin/agents/{$agent->id}/restore")->assertRedirect();

        $this->assertSame(UserStatus::ACTIVE, $agent->fresh()->status);
    }

    public function test_removing_an_agent_soft_deletes_them(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($admin)->delete("/admin/agents/{$agent->id}")
            ->assertRedirect(route('admin.agents.index'));

        $this->assertSoftDeleted('users', ['id' => $agent->id]);
    }

    public function test_a_suspended_agent_cannot_be_assigned_a_lead(): void
    {
        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->suspended()->create();
        $lead = Lead::factory()->unassigned()->create();

        $this->actingAs($staff)
            ->post("/admin/leads/{$lead->id}/assign", ['agent_id' => $agent->id])
            ->assertSessionHasErrors('agent_id');

        $this->assertNull($lead->fresh()->assigned_to);
    }

    public function test_agents_cannot_reach_the_dashboard(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/admin')->assertForbidden();
        $this->actingAs($agent)->get('/admin/agents')->assertForbidden();
    }

    public function test_staff_cannot_reach_admin_only_pages(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/admin')->assertOk();
        $this->actingAs($staff)->get('/admin/agents')->assertOk();
        $this->actingAs($staff)->get('/admin/analytics')->assertOk();

        // Admin-only surfaces.
        $this->actingAs($staff)->get('/admin/revenue')->assertForbidden();
        $this->actingAs($staff)->get('/admin/staff')->assertForbidden();
        $this->actingAs($staff)->get('/admin/content')->assertForbidden();
        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
        $this->actingAs($staff)->get('/admin/audit')->assertForbidden();
    }

    public function test_admin_reaches_every_dashboard_page(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/admin', '/admin/leads', '/admin/leads/create', '/admin/agents', '/admin/analytics',
            '/admin/revenue', '/admin/staff', '/admin/staff/create', '/admin/content',
            '/admin/content/about/edit', '/admin/settings', '/admin/audit', '/notifications'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_inactive_dashboard_users_cannot_sign_in(): void
    {
        $staff = User::factory()->staff()->suspended()->create(['password' => 'password']);

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dashboard_login_redirects_by_role(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $agent = User::factory()->agent()->create(['password' => 'password']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->post('/logout');

        $this->post('/login', ['email' => $agent->email, 'password' => 'password'])
            ->assertRedirect(route('agent.dashboard'));
    }
}
