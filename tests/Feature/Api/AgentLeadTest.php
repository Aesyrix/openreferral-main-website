<?php

namespace Tests\Feature\Api;

use App\Enums\LeadStatus;
use App\Mail\LeadFeedbackMail;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentLeadTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $otherAgent;

    private User $staff;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->staff = User::factory()->staff()->create();
        $this->agent = User::factory()->agent()->create();
        $this->otherAgent = User::factory()->agent()->create();
    }

    public function test_lead_list_is_scoped_to_the_authenticated_agent(): void
    {
        $mine = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create();
        $theirs = Lead::factory()->assignedTo($this->otherAgent->id, $this->staff->id)->create();
        Lead::factory()->unassigned()->create();

        Sanctum::actingAs($this->agent);

        $response = $this->getJson('/api/leads')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($mine->id, $response->json('data.0.id'));
        $this->assertSame(['page', 'per_page', 'total', 'last_page'], array_keys($response->json('meta')));

        $this->getJson("/api/leads/{$theirs->id}")->assertNotFound();
    }

    public function test_list_supports_the_documented_filters_and_sorts(): void
    {
        Lead::factory()->assignedTo($this->agent->id)->create([
            'name' => 'Aaron Buyer', 'intent' => 'buying', 'price_max' => 900000, 'location' => 'Miami, FL',
        ]);
        Lead::factory()->assignedTo($this->agent->id)->create([
            'name' => 'Zoe Seller', 'intent' => 'selling', 'price_max' => 200000, 'location' => 'Orlando, FL',
            'status' => LeadStatus::CONVERTED,
        ]);

        Sanctum::actingAs($this->agent);

        $this->getJson('/api/leads?intent=buying')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Aaron Buyer');

        $this->getJson('/api/leads?status=converted')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Zoe Seller');

        $this->getJson('/api/leads?q=Orlando')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Zoe Seller');

        $this->getJson('/api/leads?sort=price_high')->assertOk()
            ->assertJsonPath('data.0.name', 'Aaron Buyer');

        $this->getJson('/api/leads?sort=name_az')->assertOk()
            ->assertJsonPath('data.0.name', 'Aaron Buyer');

        $this->getJson('/api/leads?status=bogus')->assertStatus(422);
    }

    public function test_stats_returns_the_dashboard_counts(): void
    {
        Lead::factory()->count(2)->assignedTo($this->agent->id)->create();
        Lead::factory()->assignedTo($this->agent->id)->create(['status' => LeadStatus::IN_PROGRESS]);
        Lead::factory()->assignedTo($this->agent->id)->create(['status' => LeadStatus::CONVERTED]);
        Lead::factory()->assignedTo($this->otherAgent->id)->create();

        Sanctum::actingAs($this->agent);

        $this->getJson('/api/leads/stats')->assertOk()->assertJson([
            'data' => ['total' => 4, 'new' => 2, 'in_progress' => 1, 'converted' => 1, 'rejected' => 0],
        ]);
    }

    public function test_analytics_returns_a_dense_seven_day_series_and_pipeline(): void
    {
        Lead::factory()->assignedTo($this->agent->id)->create(['assigned_at' => now()]);

        Sanctum::actingAs($this->agent);

        $response = $this->getJson('/api/leads/analytics?range=week')->assertOk();

        $this->assertCount(7, $response->json('series'));
        $this->assertSame(now()->toDateString(), $response->json('series.6.date'));
        $this->assertSame(1, $response->json('series.6.count'));
        $this->assertSame(1, $response->json('pipeline.total'));
        $this->assertArrayHasKey('conversion_rate', $response->json());
    }

    public function test_accepting_a_lead_moves_it_in_progress_and_notifies_staff(): void
    {
        Mail::fake();

        $lead = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create(['lead_fee_cents' => 25000]);

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/respond", ['decision' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('feedback.decision', 'accept');

        $lead->refresh();
        $this->assertSame(LeadStatus::IN_PROGRESS, $lead->status);
        $this->assertNotNull($lead->responded_at);

        $this->assertDatabaseHas('lead_feedback', [
            'lead_id' => $lead->id, 'agent_id' => $this->agent->id, 'decision' => 'accept',
        ]);
        $this->assertDatabaseHas('lead_status_history', [
            'lead_id' => $lead->id, 'from_status' => 'new', 'to_status' => 'in_progress',
        ]);

        // Revenue: the lead fee is billed on acceptance.
        $this->assertDatabaseHas('transactions', [
            'lead_id' => $lead->id, 'agent_id' => $this->agent->id,
            'type' => 'lead_fee', 'amount_cents' => 25000, 'status' => 'completed',
        ]);

        // Events #4/#6 — staff and admin get an in-app row and an email each.
        foreach ([$this->admin, $this->staff] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id, 'type' => 'feedback', 'lead_id' => $lead->id,
            ]);
        }

        Mail::assertSent(LeadFeedbackMail::class, 2);
        $this->assertDatabaseHas('activity_logs', ['action' => 'lead.accepted', 'subject_id' => $lead->id]);
    }

    public function test_rejecting_a_lead_requires_a_reason_and_records_it(): void
    {
        Mail::fake();

        $lead = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/respond", ['decision' => 'reject'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/leads/{$lead->id}/respond", [
            'decision' => 'reject',
            'reason' => 'budget_too_low',
            'comments' => 'Below my price band.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('lead_feedback', [
            'lead_id' => $lead->id,
            'decision' => 'reject',
            'reason' => 'budget_too_low',
            'comments' => 'Below my price band.',
        ]);

        // No fee is billed for a rejection.
        $this->assertDatabaseMissing('transactions', ['lead_id' => $lead->id]);
        Mail::assertSent(LeadFeedbackMail::class, 2);
    }

    public function test_an_unknown_rejection_reason_is_rejected(): void
    {
        $lead = Lead::factory()->assignedTo($this->agent->id)->create();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/respond", ['decision' => 'reject', 'reason' => 'made_up'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_a_lead_cannot_be_responded_to_twice(): void
    {
        Mail::fake();

        $lead = Lead::factory()->assignedTo($this->agent->id)->create();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/respond", ['decision' => 'accept'])->assertOk();

        $this->postJson("/api/leads/{$lead->id}/respond", ['decision' => 'accept'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('decision');

        $this->assertSame(1, $lead->feedback()->count());
    }

    public function test_an_agent_cannot_respond_to_someone_elses_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->otherAgent->id)->create();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/respond", ['decision' => 'accept'])->assertNotFound();

        $this->assertSame(LeadStatus::NEW, $lead->fresh()->status);
    }

    public function test_notifications_endpoints_track_read_state(): void
    {
        $lead = Lead::factory()->assignedTo($this->agent->id)->create();

        $this->agent->notifications()->createMany([
            ['type' => 'new_lead', 'title' => 'New lead assigned', 'body' => 'A lead', 'lead_id' => $lead->id, 'created_at' => now()],
            ['type' => 'system', 'title' => 'Welcome', 'body' => 'Hello', 'created_at' => now()],
        ]);

        Sanctum::actingAs($this->agent);

        $this->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonPath('data.0.read', false);

        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('unread_count', 2);

        $first = $this->agent->notifications()->first();
        $this->postJson("/api/notifications/{$first->id}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('unread_count', 1);

        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_another_agents_notification_cannot_be_marked_read(): void
    {
        $theirs = $this->otherAgent->notifications()->create([
            'type' => 'system', 'title' => 'Private', 'body' => 'Not yours', 'created_at' => now(),
        ]);

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/notifications/{$theirs->id}/read")->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_device_registration_and_removal(): void
    {
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseHas('devices', ['token' => 'fcm-token-1', 'user_id' => $this->agent->id]);

        // Re-registering the same token moves it to the current user rather than duplicating.
        $this->postJson('/api/devices', ['token' => 'fcm-token-1', 'platform' => 'ios'])->assertOk();
        $this->assertSame(1, $this->agent->devices()->count());
        $this->assertSame('ios', $this->agent->devices()->first()->platform);

        $this->postJson('/api/devices', ['token' => 'x', 'platform' => 'symbian'])->assertStatus(422);

        $this->deleteJson('/api/devices/fcm-token-1')->assertOk();
        $this->assertDatabaseMissing('devices', ['token' => 'fcm-token-1']);
    }
}
