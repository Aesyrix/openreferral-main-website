<?php

namespace Tests\Feature\Dashboard;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();
        $this->agent = User::factory()->agent()->create();
    }

    public function test_staff_can_create_a_lead_with_a_generated_reference(): void
    {
        $this->actingAs($this->staff)->post('/admin/leads', [
            'name' => 'Melad Yousef',
            'phone' => '7034169995',
            'email' => 'melad@example.com',
            'intent' => 'buying',
            'property_type' => 'Single Family Home',
            'price_min' => 300000,
            'price_max' => 400000,
            'location' => 'Springfield, VA',
            'timeline' => 'ASAP',
            'bedrooms' => '3',
            'bathrooms' => '2',
            'notes' => 'Wants to move ASAP.',
            'lead_fee_dollars' => 250,
        ])->assertRedirect();

        $lead = Lead::firstOrFail();
        $this->assertSame('LEAD-10001', $lead->reference_id);
        $this->assertSame(25000, $lead->lead_fee_cents, 'Dollars are stored as cents.');
        $this->assertSame(LeadStatus::NEW, $lead->status);
        $this->assertSame($this->staff->id, $lead->created_by);
        $this->assertNull($lead->assigned_to);

        // References keep incrementing.
        $this->assertSame('LEAD-10002', Lead::nextReference());
    }

    public function test_creating_a_lead_with_an_agent_assigns_and_notifies_immediately(): void
    {
        $this->actingAs($this->staff)->post('/admin/leads', [
            'name' => 'Renee Alvarez',
            'intent' => 'buying',
            'location' => 'Orlando, FL',
            'assign_to' => $this->agent->id,
        ])->assertRedirect();

        $lead = Lead::firstOrFail();
        $this->assertSame($this->agent->id, $lead->assigned_to);
        $this->assertNotNull($lead->assigned_at);

        $this->assertDatabaseHas('lead_assignments', ['lead_id' => $lead->id, 'agent_id' => $this->agent->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->agent->id, 'type' => 'new_lead', 'lead_id' => $lead->id,
        ]);
    }

    public function test_lead_validation_rejects_a_max_budget_below_the_min(): void
    {
        $this->actingAs($this->staff)->post('/admin/leads', [
            'name' => 'Bad Budget',
            'intent' => 'buying',
            'location' => 'Miami, FL',
            'price_min' => 500000,
            'price_max' => 100000,
        ])->assertSessionHasErrors('price_max');

        $this->assertSame(0, Lead::count());
    }

    public function test_reassigning_a_lead_closes_the_previous_assignment_and_resets_to_new(): void
    {
        $other = User::factory()->agent()->create();

        $lead = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
        ]);
        $lead->assignments()->create([
            'agent_id' => $this->agent->id,
            'assigned_by' => $this->staff->id,
            'assigned_at' => now()->subDay(),
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/leads/{$lead->id}/assign", ['agent_id' => $other->id])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame($other->id, $lead->assigned_to);
        $this->assertSame(LeadStatus::NEW, $lead->status, 'The new agent must accept or reject it.');
        $this->assertNull($lead->responded_at);

        $this->assertNotNull(
            $lead->assignments()->where('agent_id', $this->agent->id)->first()->unassigned_at,
            'The old assignment row should be closed off.'
        );
        $this->assertDatabaseHas('activity_logs', ['action' => 'lead.reassigned', 'subject_id' => $lead->id]);
    }

    public function test_unassigning_returns_a_lead_to_the_pool(): void
    {
        $lead = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
        ]);

        $this->actingAs($this->staff)->post("/admin/leads/{$lead->id}/unassign")->assertRedirect();

        $lead->refresh();
        $this->assertNull($lead->assigned_to);
        $this->assertSame(LeadStatus::NEW, $lead->status);
    }

    public function test_marking_a_lead_converted_stamps_the_date_and_notifies_the_agent(): void
    {
        Mail::fake();

        $lead = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/leads/{$lead->id}/status", ['status' => 'converted'])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(LeadStatus::CONVERTED, $lead->status);
        $this->assertNotNull($lead->converted_at);

        // Event #7.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->agent->id, 'type' => 'lead_update', 'lead_id' => $lead->id,
        ]);
        $this->assertDatabaseHas('lead_status_history', [
            'lead_id' => $lead->id, 'from_status' => 'in_progress', 'to_status' => 'converted',
        ]);
    }

    public function test_an_unassigned_lead_cannot_be_pushed_through_the_pipeline(): void
    {
        $lead = Lead::factory()->unassigned()->create();

        $this->actingAs($this->staff)
            ->post("/admin/leads/{$lead->id}/status", ['status' => 'converted'])
            ->assertSessionHasErrors('status');

        $this->assertSame(LeadStatus::NEW, $lead->fresh()->status);
    }

    public function test_a_lead_can_be_edited_and_soft_deleted(): void
    {
        $lead = Lead::factory()->unassigned()->create(['name' => 'Original Name']);

        $this->actingAs($this->staff)->put("/admin/leads/{$lead->id}", [
            'name' => 'Corrected Name',
            'intent' => 'selling',
            'location' => 'Miami Beach, FL',
            'lead_fee_dollars' => 300,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('Corrected Name', $lead->name);
        $this->assertSame(30000, $lead->lead_fee_cents);

        $this->actingAs($this->staff)->delete("/admin/leads/{$lead->id}")
            ->assertRedirect(route('admin.leads.index'));

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_the_lead_list_filters_by_status_agent_and_search(): void
    {
        Lead::factory()->unassigned()->create(['name' => 'Pool Lead', 'location' => 'Tampa, FL']);
        Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create([
            'name' => 'Assigned Lead', 'status' => LeadStatus::CONVERTED, 'location' => 'Miami, FL',
        ]);

        $this->actingAs($this->staff)->get('/admin/leads?assignment=unassigned')
            ->assertOk()->assertSee('Pool Lead')->assertDontSee('Assigned Lead');

        $this->actingAs($this->staff)->get('/admin/leads?status=converted')
            ->assertOk()->assertSee('Assigned Lead')->assertDontSee('Pool Lead');

        $this->actingAs($this->staff)->get("/admin/leads?agent_id={$this->agent->id}")
            ->assertOk()->assertSee('Assigned Lead')->assertDontSee('Pool Lead');

        $this->actingAs($this->staff)->get('/admin/leads?q=Tampa')
            ->assertOk()->assertSee('Pool Lead')->assertDontSee('Assigned Lead');
    }

    public function test_the_agent_web_portal_can_accept_a_lead(): void
    {
        Mail::fake();

        $lead = Lead::factory()->assignedTo($this->agent->id, $this->staff->id)->create();

        $this->actingAs($this->agent)->get("/agent/leads/{$lead->id}")->assertOk();

        $this->actingAs($this->agent)
            ->post("/agent/leads/{$lead->id}/respond", ['decision' => 'accept'])
            ->assertRedirect(route('agent.leads.show', $lead->id));

        $this->assertSame(LeadStatus::IN_PROGRESS, $lead->fresh()->status);
    }

    public function test_an_agent_cannot_open_another_agents_lead_on_the_web(): void
    {
        $other = User::factory()->agent()->create();
        $lead = Lead::factory()->assignedTo($other->id, $this->staff->id)->create();

        $this->actingAs($this->agent)->get("/agent/leads/{$lead->id}")->assertNotFound();
        $this->actingAs($this->agent)->post("/agent/leads/{$lead->id}/respond", ['decision' => 'accept'])
            ->assertNotFound();
    }

    public function test_global_search_jumps_straight_to_an_exact_reference(): void
    {
        $lead = Lead::factory()->unassigned()->create();

        $this->actingAs($this->staff)->get('/search?q='.$lead->reference_id)
            ->assertRedirect(route('admin.leads.show', $lead->id));
    }
}
