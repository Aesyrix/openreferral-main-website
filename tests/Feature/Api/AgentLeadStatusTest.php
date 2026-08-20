<?php

namespace Tests\Feature\Api;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /api/leads/{id}/status — the agent moving their own lead through the
 * pipeline after accepting it.
 */
class AgentLeadStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $staff;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = User::factory()->admin()->create();
        $this->staff = User::factory()->staff()->create();
        $this->agent = User::factory()->agent()->create();
    }

    private function acceptedLead(): Lead
    {
        return Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
            'responded_at' => now()->subHour(),
        ]);
    }

    public function test_agent_marks_an_accepted_lead_converted(): void
    {
        $lead = $this->acceptedLead();

        Sanctum::actingAs($this->agent);

        $response = $this->postJson("/api/leads/{$lead->id}/status", [
            'status' => 'converted',
            'comments' => 'Closed today, contract signed.',
        ])->assertOk()->assertJsonPath('data.status', 'converted');

        // Same envelope and fields as GET /leads/{id} — the app reuses one model.
        $this->assertSame(
            array_keys($this->getJson("/api/leads/{$lead->id}")->json('data')),
            array_keys($response->json('data'))
        );

        $lead->refresh();
        $this->assertSame(LeadStatus::CONVERTED, $lead->status);
        $this->assertNotNull($lead->converted_at);

        $this->assertDatabaseHas('lead_status_history', [
            'lead_id' => $lead->id,
            'from_status' => 'in_progress',
            'to_status' => 'converted',
            'changed_by' => $this->agent->id,
        ]);

        // Staff and admin are told about it.
        foreach ([$this->admin, $this->staff] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'type' => 'lead_update',
                'lead_id' => $lead->id,
                'actor_id' => $this->agent->id,
            ]);
        }

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'lead.status_changed',
            'subject_id' => $lead->id,
        ]);
    }

    public function test_a_late_rejection_records_the_reason_as_feedback(): void
    {
        $lead = $this->acceptedLead();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/status", [
            'status' => 'rejected',
            'reason' => 'already_working_other',
            'comments' => 'Client signed with someone else.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('lead_feedback', [
            'lead_id' => $lead->id,
            'agent_id' => $this->agent->id,
            'decision' => 'reject',
            'reason' => 'already_working_other',
            'comments' => 'Client signed with someone else.',
        ]);

        $this->assertSame(LeadStatus::REJECTED, $lead->fresh()->status);
    }

    public function test_moving_back_from_converted_clears_the_conversion_date(): void
    {
        $lead = $this->acceptedLead();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'converted'])->assertOk();
        $this->assertNotNull($lead->fresh()->converted_at);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'in_progress'])->assertOk();

        $lead->refresh();
        $this->assertSame(LeadStatus::IN_PROGRESS, $lead->status);
        $this->assertNull($lead->converted_at);
    }

    public function test_a_new_lead_must_be_accepted_first(): void
    {
        $lead = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'converted'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(LeadStatus::NEW, $lead->fresh()->status);
    }

    public function test_another_agents_lead_is_forbidden(): void
    {
        $other = User::factory()->agent()->create();
        $lead = Lead::factory()->assignedTo($other->id, $this->staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
        ]);

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'converted'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'lead_not_assigned');

        $this->assertSame(LeadStatus::IN_PROGRESS, $lead->fresh()->status);
    }

    public function test_a_missing_lead_is_a_404(): void
    {
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/leads/999999/status', ['status' => 'converted'])->assertNotFound();
    }

    public function test_the_status_and_reason_values_are_validated(): void
    {
        $lead = $this->acceptedLead();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'closed_won'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        // `new` is deliberately not an allowed target — /respond owns that.
        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'new'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'rejected', 'reason' => 'made_up'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/leads/{$lead->id}/status", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_rejecting_without_a_reason_is_accepted(): void
    {
        // The app's contract marks `reason` optional; staff see "not given".
        $lead = $this->acceptedLead();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('lead_feedback', [
            'lead_id' => $lead->id,
            'decision' => 'reject',
            'reason' => null,
        ]);
    }

    public function test_a_no_op_repeat_does_not_duplicate_history(): void
    {
        $lead = $this->acceptedLead();

        Sanctum::actingAs($this->agent);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'in_progress'])->assertOk();

        $this->assertSame(0, $lead->statusHistory()->count());
    }

    public function test_a_pending_agent_cannot_use_the_endpoint(): void
    {
        $pending = User::factory()->pending()->create();
        $lead = Lead::factory()->assignedTo($pending->id, $this->staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
        ]);

        Sanctum::actingAs($pending);

        $this->postJson("/api/leads/{$lead->id}/status", ['status' => 'converted'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_not_active');
    }

    public function test_the_change_shows_up_in_the_staff_dashboard(): void
    {
        $lead = $this->acceptedLead();

        Sanctum::actingAs($this->agent);
        $this->postJson("/api/leads/{$lead->id}/status", [
            'status' => 'converted',
            'comments' => 'Deal closed.',
        ])->assertOk();

        $this->actingAs($this->staff)->get("/admin/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Converted');
    }
}
