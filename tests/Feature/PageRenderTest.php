<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\ContentPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every GET page must render with real data — a cheap guard against a broken
 * Blade reference or a route rename slipping through.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_dashboard_page_renders_for_an_admin(): void
    {
        $this->seed(ContentPageSeeder::class);

        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->create();
        $agent->serviceAreas()->create(['area' => 'Miami, FL']);

        // A lead with a full history so the detail page exercises every panel.
        $lead = Lead::factory()->assignedTo($agent->id, $staff->id)->create([
            'status' => LeadStatus::IN_PROGRESS,
            'responded_at' => now(),
        ]);
        $lead->assignments()->create(['agent_id' => $agent->id, 'assigned_by' => $staff->id, 'assigned_at' => now()->subHour()]);
        $lead->feedback()->create(['agent_id' => $agent->id, 'decision' => 'accept', 'comments' => 'On it.', 'created_at' => now()]);
        $lead->statusHistory()->create(['from_status' => 'new', 'to_status' => 'in_progress', 'changed_by' => $agent->id, 'created_at' => now()]);
        $lead->transactions()->create([
            'agent_id' => $agent->id, 'type' => 'lead_fee', 'amount_cents' => 25000,
            'status' => 'completed', 'created_at' => now(),
        ]);

        $rejected = Lead::factory()->assignedTo($agent->id, $staff->id)->create(['status' => LeadStatus::REJECTED]);
        $rejected->feedback()->create([
            'agent_id' => $agent->id, 'decision' => 'reject',
            'reason' => 'budget_too_low', 'created_at' => now(),
        ]);

        $admin->notifications()->create([
            'type' => 'feedback', 'title' => 'Agent accepted a lead', 'body' => 'Details',
            'lead_id' => $lead->id, 'actor_id' => $agent->id, 'created_at' => now(),
        ]);

        $pages = [
            '/admin',
            '/admin/leads',
            '/admin/leads?status=new',
            '/admin/leads?assignment=unassigned&sort=price_high',
            '/admin/leads/create',
            "/admin/leads/{$lead->id}",
            "/admin/leads/{$lead->id}/edit",
            "/admin/leads/{$lead->id}/feedback",
            '/admin/agents',
            '/admin/agents?status=active&q=a',
            "/admin/agents/{$agent->id}",
            '/admin/analytics',
            '/admin/analytics?range=quarter',
            '/admin/revenue',
            '/admin/revenue?range=year&status=completed',
            '/admin/staff',
            '/admin/staff/create',
            "/admin/staff/{$staff->id}/edit",
            '/admin/content',
            '/admin/content/about/edit',
            '/admin/content/help/edit',
            '/admin/settings',
            '/admin/audit',
            '/notifications',
            '/notifications?unread=1',
            '/search?q=Miami',
        ];

        foreach ($pages as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk($uri);
        }
    }

    public function test_every_agent_portal_page_renders(): void
    {
        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->create();

        $awaiting = Lead::factory()->assignedTo($agent->id, $staff->id)->create();
        $accepted = Lead::factory()->assignedTo($agent->id, $staff->id)->create([
            'status' => LeadStatus::CONVERTED,
            'responded_at' => now(),
            'converted_at' => now(),
        ]);
        $accepted->feedback()->create(['agent_id' => $agent->id, 'decision' => 'accept', 'created_at' => now()]);

        foreach ([
            '/agent',
            '/agent/leads',
            '/agent/leads?status=new',
            "/agent/leads/{$awaiting->id}",
            "/agent/leads/{$accepted->id}",
            '/agent/profile',
            '/notifications',
        ] as $uri) {
            $this->actingAs($agent)->get($uri)->assertOk($uri);
        }
    }

    public function test_guest_pages_render(): void
    {
        foreach (['/login', '/forgot-password', '/reset-password?email=a@b.com'] as $uri) {
            $this->get($uri)->assertOk($uri);
        }

        $this->get('/')->assertRedirect('/login');
    }

    public function test_staff_land_on_the_shared_dashboard(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/staff')->assertRedirect('/admin');
        $this->actingAs($staff)->get('/admin')->assertOk();
    }
}
