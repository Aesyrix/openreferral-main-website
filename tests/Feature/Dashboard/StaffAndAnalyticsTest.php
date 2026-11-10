<?php

namespace Tests\Feature\Dashboard;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Mail\StaffInviteMail;
use App\Models\ContentPage;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_a_staff_member(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Ethan Bennett',
            'email' => 'Ethan.B@Example.com',
            'phone' => '+1 305 555 0101',
            'role' => 'staff',
        ])->assertRedirect(route('admin.staff.index'));

        $staff = User::where('email', 'ethan.b@example.com')->firstOrFail();
        $this->assertSame(Role::STAFF, $staff->role);
        $this->assertSame(UserStatus::ACTIVE, $staff->status);
        $this->assertSame($admin->id, $staff->created_by);
        $this->assertNotNull($staff->settings);

        Mail::assertSent(StaffInviteMail::class, 1);
        $this->assertDatabaseHas('email_log', ['event' => 'staff_invite', 'to_email' => 'ethan.b@example.com']);
    }

    public function test_the_last_admin_cannot_be_demoted_suspended_or_removed(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->staff()->create();

        $this->actingAs($admin)->put("/admin/staff/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'staff',
        ])->assertSessionHas('error');

        $this->assertSame(Role::ADMIN, $admin->fresh()->role);

        $this->actingAs($admin)->post("/admin/staff/{$admin->id}/suspend")->assertSessionHas('error');
        $this->assertSame(UserStatus::ACTIVE, $admin->fresh()->status);

        // A second admin makes the first removable again.
        $secondAdmin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete("/admin/staff/{$secondAdmin->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $secondAdmin->id]);

        // Staff are removable regardless.
        $this->actingAs($admin)->delete("/admin/staff/{$other->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_staff_cannot_create_staff(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post('/admin/staff', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'admin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_admin_can_publish_app_content(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/content/terms', [
            'title' => 'Terms of Service',
            'intro' => 'The agreement.',
            'sections' => [
                ['heading' => 'Eligibility', 'body' => 'You must hold a valid license.'],
                ['heading' => 'Fees', 'body' => 'A referral fee applies per accepted lead.'],
            ],
        ])->assertRedirect(route('admin.content.index'));

        $page = ContentPage::where('slug', 'terms')->firstOrFail();
        $this->assertCount(2, $page->sections());
        $this->assertSame('Eligibility', $page->sections()[0]['heading']);

        // Immediately visible to the app.
        $this->getJson('/api/content/terms')->assertOk()->assertJsonPath('sections.1.heading', 'Fees');
    }

    public function test_content_requires_at_least_one_section_and_a_known_slug(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/content/about', ['title' => 'About'])
            ->assertSessionHasErrors('sections');

        $this->actingAs($admin)->put('/admin/content/bogus', [
            'title' => 'X', 'sections' => [['heading' => 'H', 'body' => 'B']],
        ])->assertNotFound();
    }

    public function test_admin_can_update_settings_and_they_take_effect(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings', [
            'company_name' => 'Open Referral Network',
            'support_email' => 'help@openreferral.us',
            'default_lead_fee_dollars' => 400,
            'otp_ttl_minutes' => 15,
            'otp_max_attempts' => 4,
            'otp_resend_seconds' => 60,
        ])->assertRedirect();

        $this->assertSame('400', (string) Setting::get('default_lead_fee_dollars'));
        $this->assertSame('15', (string) Setting::get('otp_ttl_minutes'));

        // A lead created without an explicit fee picks up the new default.
        $this->actingAs($admin)->post('/admin/leads', [
            'name' => 'Default Fee Client', 'intent' => 'buying', 'location' => 'Miami, FL',
        ])->assertRedirect();

        $this->assertSame(40000, Lead::firstOrFail()->lead_fee_cents);
    }

    public function test_revenue_totals_only_count_completed_transactions(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        Transaction::create(['agent_id' => $agent->id, 'type' => 'lead_fee', 'amount_cents' => 25000, 'status' => 'completed']);
        Transaction::create(['agent_id' => $agent->id, 'type' => 'lead_fee', 'amount_cents' => 30000, 'status' => 'completed']);
        Transaction::create(['agent_id' => $agent->id, 'type' => 'lead_fee', 'amount_cents' => 99900, 'status' => 'pending']);
        Transaction::create(['agent_id' => $agent->id, 'type' => 'adjustment', 'amount_cents' => -5000, 'status' => 'completed']);

        $this->assertSame(50000, app(AnalyticsService::class)->totalRevenueCents());

        $this->actingAs($admin)->get('/admin/revenue')->assertOk()->assertSee('500.00');
    }

    public function test_admin_can_record_and_restatus_a_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($admin)->post('/admin/revenue/transactions', [
            'agent_id' => $agent->id,
            'amount_dollars' => 199.50,
            'type' => 'subscription',
            'status' => 'completed',
            'reference' => 'stripe_abc',
        ])->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(19950, $transaction->amount_cents);

        $this->actingAs($admin)->post("/admin/revenue/transactions/{$transaction->id}/status", ['status' => 'refunded'])
            ->assertRedirect();

        $this->assertSame('refunded', $transaction->fresh()->status);
        $this->assertSame(0, app(AnalyticsService::class)->totalRevenueCents());
    }

    public function test_analytics_computes_conversion_acceptance_and_the_agent_scorecard(): void
    {
        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->create();

        // 4 assigned: 1 converted, 1 in progress, 1 rejected, 1 still new.
        $converted = Lead::factory()->assignedTo($agent->id, $staff->id)->create([
            'status' => LeadStatus::CONVERTED,
            'assigned_at' => now()->subHours(6),
            'responded_at' => now()->subHours(4),
            'converted_at' => now()->subHour(),
        ]);
        $inProgress = Lead::factory()->assignedTo($agent->id, $staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
            'assigned_at' => now()->subHours(4),
            'responded_at' => now()->subHours(2),
        ]);
        $rejected = Lead::factory()->assignedTo($agent->id, $staff->id)->create([
            'status' => LeadStatus::REJECTED,
            'assigned_at' => now()->subHours(3),
            'responded_at' => now()->subHours(1),
        ]);
        Lead::factory()->assignedTo($agent->id, $staff->id)->create();

        foreach ([[$converted, 'accept', null], [$inProgress, 'accept', null], [$rejected, 'reject', 'budget_too_low']] as [$lead, $decision, $reason]) {
            $lead->feedback()->create([
                'agent_id' => $agent->id,
                'decision' => $decision,
                'reason' => $reason,
                'created_at' => $lead->responded_at,
            ]);
        }

        $analytics = app(AnalyticsService::class);

        $this->assertSame(['total' => 4, 'new' => 1, 'in_progress' => 1, 'converted' => 1, 'rejected' => 1], $analytics->statusCounts());
        $this->assertSame(25.0, $analytics->conversionRate());
        $this->assertEqualsWithDelta(66.7, $analytics->acceptanceRate(), 0.1);
        $this->assertSame(2.0, $analytics->avgResponseHours());

        $reasons = $analytics->rejectionReasons();
        $this->assertSame('budget_too_low', $reasons[0]['reason']);
        $this->assertSame(100.0, $reasons[0]['percent']);

        $row = $analytics->agentScorecard()->firstWhere('agent.id', $agent->id);
        $this->assertSame(4, $row->assigned);
        $this->assertSame(2, $row->accepted);
        $this->assertSame(1, $row->rejected);
        $this->assertSame(1, $row->converted);
        $this->assertSame(25.0, $row->conversion_rate);
        $this->assertSame(2.0, $row->avg_response_hours);

        $this->actingAs($staff)->get('/admin/analytics')->assertOk()->assertSee('Budget too low');
    }

    public function test_analytics_series_endpoint_returns_dense_json(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/admin/analytics/series?range=week')->assertOk();

        $this->assertCount(7, $response->json('leads'));
        $this->assertCount(7, $response->json('conversions'));
        $this->assertArrayHasKey('pipeline', $response->json());
    }

    public function test_the_audit_log_lists_privileged_actions(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->pending()->create();

        $this->actingAs($admin)->post("/admin/agents/{$agent->id}/approve");

        $this->actingAs($admin)->get('/admin/audit')->assertOk()->assertSee('agent.approved');
        $this->actingAs($admin)->get('/admin/audit?action=agent.approved')->assertOk()->assertSee('agent.approved');
    }
}
