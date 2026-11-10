<?php

namespace Tests\Feature\Dashboard;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFeedback;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** An agent with leads, feedback, revenue, devices and notifications. */
    private function agentWithHistory(User $staff): User
    {
        $agent = User::factory()->agent()->create();
        $agent->devices()->create(['token' => 'fcm-'.$agent->id, 'platform' => 'android']);
        $agent->createToken('mobile_agent');
        $agent->serviceAreas()->create(['area' => 'Miami, FL']);

        $open = Lead::factory()->assignedTo($agent->id, $staff->id)->create();
        $converted = Lead::factory()->assignedTo($agent->id, $staff->id)->create([
            'status' => LeadStatus::CONVERTED,
            'responded_at' => now(),
            'converted_at' => now(),
        ]);

        LeadAssignment::create([
            'lead_id' => $converted->id, 'agent_id' => $agent->id,
            'assigned_by' => $staff->id, 'assigned_at' => now()->subDay(),
        ]);

        LeadFeedback::create([
            'lead_id' => $converted->id, 'agent_id' => $agent->id,
            'decision' => 'reject', 'reason' => 'budget_too_low', 'created_at' => now(),
        ]);

        Transaction::create([
            'agent_id' => $agent->id, 'lead_id' => $converted->id, 'type' => 'lead_fee',
            'amount_cents' => 25000, 'status' => 'completed', 'created_at' => now(),
        ]);

        Notification::create([
            'user_id' => $agent->id, 'type' => 'new_lead', 'title' => 'New lead assigned',
            'body' => 'A lead', 'lead_id' => $open->id, 'created_at' => now(),
        ]);

        return $agent;
    }

    public function test_deleting_an_agent_erases_them_but_keeps_revenue_and_analytics(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $agent = $this->agentWithHistory($staff);

        $revenueBefore = app(AnalyticsService::class)->totalRevenueCents();
        $this->assertSame(25000, $revenueBefore);

        $this->actingAs($admin)
            ->delete("/admin/agents/{$agent->id}", ['confirm_name' => $agent->name])
            ->assertRedirect(route('admin.agents.index'));

        // The user is gone from the table entirely.
        $this->assertDatabaseMissing('users', ['id' => $agent->id]);
        $this->assertSame(0, User::withTrashed()->where('id', $agent->id)->count());

        // Personal data goes with them.
        $this->assertDatabaseMissing('agent_profiles', ['user_id' => $agent->id]);
        $this->assertDatabaseMissing('agent_service_areas', ['user_id' => $agent->id]);
        $this->assertDatabaseMissing('user_settings', ['user_id' => $agent->id]);
        $this->assertDatabaseMissing('devices', ['user_id' => $agent->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $agent->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $agent->id]);

        // Money survives, detached from the person — revenue must not move.
        $this->assertSame($revenueBefore, app(AnalyticsService::class)->totalRevenueCents());
        $this->assertSame(1, Transaction::whereNull('agent_id')->count());

        // So does the analytics history.
        $this->assertSame(1, LeadFeedback::whereNull('agent_id')->count());
        $this->assertSame(1, LeadAssignment::whereNull('agent_id')->count());

        // Rejection-reason analytics still see the record.
        $reasons = app(AnalyticsService::class)->rejectionReasons();
        $this->assertSame('budget_too_low', $reasons[0]['reason']);
    }

    public function test_leads_survive_and_open_ones_return_to_the_pool(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $agent = $this->agentWithHistory($staff);

        $leadIds = Lead::where('assigned_to', $agent->id)->pluck('id');
        $this->assertCount(2, $leadIds);

        $this->actingAs($admin)->delete("/admin/agents/{$agent->id}", ['confirm_name' => $agent->name]);

        foreach ($leadIds as $id) {
            $lead = Lead::find($id);
            $this->assertNotNull($lead, 'Leads must outlive the agent.');
            $this->assertNull($lead->assigned_to);
        }

        $this->assertSame(2, Lead::whereNull('assigned_to')->count());
    }

    public function test_the_audit_trail_records_the_deletion(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create(['name' => 'Carlos Rodriguez']);

        $this->actingAs($admin)->delete("/admin/agents/{$agent->id}", ['confirm_name' => 'Carlos Rodriguez']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.deleted_permanently',
            'subject_id' => $agent->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_a_mistyped_name_deletes_nothing(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create(['name' => 'Carlos Rodriguez']);

        $this->actingAs($admin)
            ->delete("/admin/agents/{$agent->id}", ['confirm_name' => 'carlos rodriguez typo'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $agent->id]);

        // Missing entirely is a validation error, still no deletion.
        $this->actingAs($admin)
            ->delete("/admin/agents/{$agent->id}")
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseHas('users', ['id' => $agent->id]);
    }

    public function test_deleting_a_staff_member_keeps_the_leads_they_created(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        $lead = Lead::factory()->unassigned()->create(['created_by' => $staff->id]);

        $this->actingAs($admin)->delete("/admin/staff/{$staff->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);

        $lead->refresh();
        $this->assertNotNull($lead, 'The lead must survive its creator.');
        $this->assertNull($lead->created_by);
    }

    public function test_the_delete_confirmation_shows_the_impact(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $agent = $this->agentWithHistory($staff);

        $this->actingAs($admin)->get("/admin/agents/{$agent->id}")
            ->assertOk()
            ->assertSee('Delete permanently')
            ->assertSee('Kept (person detached)')
            ->assertSee('250.00')            // the revenue that survives
            ->assertSee('to confirm');
    }

    public function test_staff_cannot_delete_an_admin(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($staff)->delete("/admin/staff/{$admin->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
