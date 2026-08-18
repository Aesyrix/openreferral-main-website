<?php

namespace Database\Seeders;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AgentServiceArea;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadFeedback;
use App\Models\LeadStatusHistory;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\ActivityLogger;
use Illuminate\Database\Seeder;

/**
 * Demo data: one admin, two staff, four agents (one pending, one suspended),
 * the four content pages, and a lead set that exercises every status.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ContentPageSeeder::class);

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::set($key, $value);
        }

        // ------------------------------------------------------------ People

        $admin = $this->user('Aaron Smith', 'admin@openreferral.us', Role::ADMIN, '+1 (888) 568-1757');

        $staffA = $this->user('Ethan Bennett', 'ethan.b@openreferral.us', Role::STAFF, '+1 (305) 555-0101', $admin);
        $staffB = $this->user('Laura Chen', 'laura.c@openreferral.us', Role::STAFF, '+1 (305) 555-0102', $admin);

        $carlos = $this->agent('Carlos Rodriguez', 'carlos.r@example.net', '+1 (305) 982-4187', [
            'title' => 'Senior Luxury Agent',
            'verified' => true,
            'license_number' => 'FL-SL349182',
            'license_state' => 'FL',
            'brokerage' => 'Premier South Realty',
            'bio' => 'South Florida luxury residential and waterfront specialist.',
        ], ['Miami, FL', 'Miami Beach, FL', 'Coral Gables, FL'], $admin);

        $christopher = $this->agent('Christopher Hale', 'christopher@example.com', '+1 (703) 555-0199', [
            'title' => 'Virginia Residential Specialist',
            'verified' => true,
            'license_number' => 'VA-02251982',
            'license_state' => 'VA',
            'brokerage' => 'Virginia Premier Realty',
            'bio' => 'Northern Virginia buyers and sellers, 12 years in the market.',
        ], ['Springfield, VA', 'Manassas, VA', 'Merrifield, VA'], $admin);

        $priya = $this->agent('Priya Nair', 'priya.n@example.com', '+1 (407) 555-0143', [
            'title' => 'Orlando Buyer Specialist',
            'license_number' => 'FL-BK774120',
            'license_state' => 'FL',
            'brokerage' => 'Sunshine Realty Group',
            'bio' => 'First-time buyers and relocation clients across Greater Orlando.',
        ], ['Orlando, FL', 'Winter Park, FL'], $admin);

        // Pending — sits in the approval queue on the dashboard.
        $pending = $this->agent('Sarah Whitfield', 'sarah.w@example.com', '+1 (407) 555-0188', [
            'title' => 'Orlando Real Estate Agent',
            'license_number' => 'FL-BK982410',
            'license_state' => 'FL',
            'brokerage' => 'Orlando Sunshine Realty',
        ], ['Orlando, FL'], null, UserStatus::PENDING);

        // Suspended — shows the reactivate path.
        $this->agent('Derek Olsen', 'derek.o@example.com', '+1 (786) 555-0170', [
            'title' => 'Broward Agent',
            'license_number' => 'FL-SL220981',
            'license_state' => 'FL',
            'brokerage' => 'Coastline Partners',
        ], ['Fort Lauderdale, FL'], $admin, UserStatus::SUSPENDED);

        Notification::create([
            'user_id' => $admin->id,
            'type' => 'agent_signup',
            'title' => 'New agent awaiting approval',
            'body' => "{$pending->name} ({$pending->email}) signed up and is pending approval.",
            'actor_id' => $pending->id,
            'created_at' => now()->subHours(3),
        ]);

        // ------------------------------------------------------------- Leads

        // 1. Accepted and in progress.
        $melad = $this->lead([
            'reference_id' => 'LEAD-10001',
            'name' => 'Melad Yousef',
            'phone' => '7034169995',
            'email' => 'melad.yousef@example.com',
            'intent' => LeadIntent::BUYING,
            'property_type' => 'Single Family Home',
            'price_min' => 300000,
            'price_max' => 400000,
            'location' => 'Springfield, Merrifield, Manassas, Virginia',
            'timeline' => 'ASAP',
            'bedrooms' => '3',
            'bathrooms' => '2',
            'source' => 'Manual ISA',
            'notes' => "Wants to move as soon as possible and is happy to be contacted any time.\nPre-approved with a local lender.",
            'lead_fee_cents' => 25000,
            'created_by' => $staffA->id,
            'created_at' => now()->subDays(6),
        ]);
        $this->assign($melad, $christopher, $staffA, now()->subDays(6)->addHours(1));
        $this->respond($melad, $christopher, 'accept', null, 'Accepted — calling the client now.', now()->subDays(6)->addHours(3));

        // 2. Converted.
        $angela = $this->lead([
            'name' => 'Angela Brooks',
            'phone' => '+1 (305) 555-0221',
            'email' => 'angela.brooks@example.com',
            'intent' => LeadIntent::SELLING,
            'property_type' => 'Condo',
            'price_min' => 550000,
            'price_max' => 640000,
            'location' => 'Miami Beach, FL',
            'timeline' => 'Within 60 days',
            'bedrooms' => '2',
            'bathrooms' => '2',
            'source' => 'Website',
            'notes' => 'Downsizing after retirement. Wants a valuation first.',
            'lead_fee_cents' => 30000,
            'created_by' => $staffB->id,
            'created_at' => now()->subDays(25),
        ]);
        $this->assign($angela, $carlos, $staffB, now()->subDays(25)->addHours(2));
        $this->respond($angela, $carlos, 'accept', null, 'Know the building well.', now()->subDays(25)->addHours(4));
        $this->convert($angela, $staffB, now()->subDays(4));

        // 3. Rejected with a reason.
        $tomas = $this->lead([
            'name' => 'Tomas Ferreira',
            'phone' => '+1 (305) 555-0244',
            'email' => 'tomas.f@example.com',
            'intent' => LeadIntent::BUYING,
            'property_type' => 'Townhouse',
            'price_min' => 140000,
            'price_max' => 180000,
            'location' => 'Homestead, FL',
            'timeline' => '6+ months',
            'bedrooms' => '2',
            'bathrooms' => '1',
            'source' => 'Facebook Ads',
            'notes' => 'Budget is tight and timeline is loose.',
            'lead_fee_cents' => 20000,
            'created_by' => $staffA->id,
            'created_at' => now()->subDays(11),
        ]);
        $this->assign($tomas, $carlos, $staffA, now()->subDays(11)->addHours(1));
        $this->respond($tomas, $carlos, 'reject', 'budget_too_low', 'Below the price band I work in — worth passing to a Homestead agent.', now()->subDays(11)->addHours(6));

        // 4. Assigned, still waiting on the agent.
        $renee = $this->lead([
            'name' => 'Renee Alvarez',
            'phone' => '+1 (407) 555-0301',
            'email' => 'renee.alvarez@example.com',
            'intent' => LeadIntent::BUYING,
            'property_type' => 'Single Family Home',
            'price_min' => 380000,
            'price_max' => 450000,
            'location' => 'Winter Park, FL',
            'timeline' => 'Within 30 days',
            'bedrooms' => '4',
            'bathrooms' => '3',
            'source' => 'Referral',
            'notes' => 'Relocating for work in October. Needs school district info.',
            'lead_fee_cents' => 25000,
            'created_by' => $staffB->id,
            'created_at' => now()->subHours(20),
        ]);
        $this->assign($renee, $priya, $staffB, now()->subHours(18));

        // 5 & 6. Unassigned pool.
        $this->lead([
            'name' => 'Jordan Blake',
            'phone' => '+1 (786) 555-0410',
            'email' => 'jordan.blake@example.com',
            'intent' => LeadIntent::SELLING,
            'property_type' => 'Single Family Home',
            'price_min' => 720000,
            'price_max' => 800000,
            'location' => 'Coral Gables, FL',
            'timeline' => 'Within 90 days',
            'bedrooms' => '4',
            'bathrooms' => '3',
            'source' => 'Website',
            'notes' => 'Inherited property, wants a market appraisal before listing.',
            'lead_fee_cents' => 35000,
            'created_by' => $staffA->id,
            'created_at' => now()->subHours(5),
        ]);

        $this->lead([
            'name' => 'Nadia Okafor',
            'phone' => '+1 (305) 555-0512',
            'email' => 'nadia.okafor@example.com',
            'intent' => LeadIntent::BUYING,
            'property_type' => 'Condo',
            'price_min' => 260000,
            'price_max' => 320000,
            'location' => 'Brickell, Miami, FL',
            'timeline' => 'ASAP',
            'bedrooms' => '1',
            'bathrooms' => '1',
            'source' => 'Google Ads',
            'notes' => 'Cash buyer, investment purchase.',
            'lead_fee_cents' => 25000,
            'created_by' => $staffA->id,
            'created_at' => now()->subHours(2),
        ]);

        // A spread of historical leads so the charts and scorecard have shape.
        $agents = [$carlos, $christopher, $priya];

        for ($i = 0; $i < 34; $i++) {
            $createdAt = now()->subDays(rand(1, 28))->subHours(rand(0, 20));
            $agent = $agents[$i % 3];

            $lead = $this->lead([
                'name' => 'Client '.fake()->firstName().' '.fake()->lastName(),
                'phone' => '+1 (305) 555-'.str_pad((string) (600 + $i), 4, '0', STR_PAD_LEFT),
                'email' => 'client'.($i + 10).'@example.com',
                'intent' => $i % 2 === 0 ? LeadIntent::BUYING : LeadIntent::SELLING,
                'property_type' => ['Single Family Home', 'Condo', 'Townhouse', 'Multi-Family'][$i % 4],
                'price_min' => 250000 + ($i % 6) * 50000,
                'price_max' => 350000 + ($i % 6) * 60000,
                'location' => ['Miami, FL', 'Springfield, VA', 'Orlando, FL', 'Manassas, VA'][$i % 4],
                'timeline' => ['ASAP', 'Within 30 days', 'Within 60 days', '6+ months'][$i % 4],
                'bedrooms' => (string) (2 + $i % 3),
                'bathrooms' => (string) (1 + $i % 3),
                'source' => ['Website', 'Referral', 'Google Ads', 'Manual ISA'][$i % 4],
                'notes' => 'Qualified enquiry captured by the ISA team.',
                'lead_fee_cents' => [20000, 25000, 30000][$i % 3],
                'created_by' => $i % 2 === 0 ? $staffA->id : $staffB->id,
                'created_at' => $createdAt,
            ]);

            // Leave every 7th lead unassigned, and every 5th awaiting a response.
            if ($i % 7 === 0) {
                continue;
            }

            $assignedAt = (clone $createdAt)->addHours(rand(1, 5));
            $this->assign($lead, $agent, $staffA, $assignedAt);

            if ($i % 5 === 0) {
                continue;
            }

            $respondedAt = (clone $assignedAt)->addHours(rand(1, 30));

            if ($i % 4 === 3) {
                $reasons = ['not_in_service_area', 'not_interested', 'budget_too_low', 'already_working_other', 'not_serious'];
                $this->respond($lead, $agent, 'reject', $reasons[$i % 5], null, $respondedAt);

                continue;
            }

            $this->respond($lead, $agent, 'accept', null, null, $respondedAt);

            if ($i % 3 === 0) {
                $this->convert($lead, $staffB, (clone $respondedAt)->addDays(rand(2, 12)));
            }
        }
    }

    // ------------------------------------------------------------- Helpers

    private function user(string $name, string $email, Role $role, ?string $phone = null, ?User $creator = null): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'password',
            'role' => $role,
            'status' => UserStatus::ACTIVE,
            'created_by' => $creator?->id,
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(rand(1, 40)),
        ]);

        UserSetting::create(['user_id' => $user->id]);

        return $user;
    }

    private function agent(
        string $name,
        string $email,
        string $phone,
        array $profile,
        array $areas,
        ?User $approver = null,
        UserStatus $status = UserStatus::ACTIVE,
    ): User {
        $agent = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'password',
            'role' => Role::AGENT,
            'status' => $status,
            'email_verified_at' => now(),
            'last_login_at' => $status === UserStatus::PENDING ? null : now()->subHours(rand(1, 60)),
        ]);

        AgentProfile::create($profile + [
            'user_id' => $agent->id,
            'approved_by' => $approver?->id,
            'approved_at' => $approver ? now()->subDays(rand(30, 90)) : null,
        ]);

        foreach ($areas as $area) {
            AgentServiceArea::create(['user_id' => $agent->id, 'area' => $area]);
        }

        UserSetting::create(['user_id' => $agent->id]);

        return $agent;
    }

    private function lead(array $attributes): Lead
    {
        $lead = Lead::create($attributes + ['status' => LeadStatus::NEW]);

        // created_at is guarded by Eloquent's timestamps, so backdate explicitly.
        if (isset($attributes['created_at'])) {
            $lead->forceFill([
                'created_at' => $attributes['created_at'],
                'updated_at' => $attributes['created_at'],
            ])->saveQuietly();
        }

        return $lead->refresh();
    }

    private function assign(Lead $lead, User $agent, User $staff, $at): void
    {
        $lead->forceFill([
            'assigned_to' => $agent->id,
            'assigned_by' => $staff->id,
            'assigned_at' => $at,
            'status' => LeadStatus::NEW,
        ])->saveQuietly();

        LeadAssignment::create([
            'lead_id' => $lead->id,
            'agent_id' => $agent->id,
            'assigned_by' => $staff->id,
            'assigned_at' => $at,
        ]);

        Notification::create([
            'user_id' => $agent->id,
            'type' => 'new_lead',
            'title' => 'New lead assigned',
            'body' => "{$lead->reference_id} · {$lead->name} ({$lead->intent?->label()}) in {$lead->location}.",
            'lead_id' => $lead->id,
            'actor_id' => $staff->id,
            'read_at' => $at,
            'created_at' => $at,
        ]);

        ActivityLogger::lead('lead.assigned', $lead->id, ['agent_id' => $agent->id], $staff);
    }

    private function respond(Lead $lead, User $agent, string $decision, ?string $reason, ?string $comments, $at): void
    {
        $to = $decision === 'accept' ? LeadStatus::IN_PROGRESS : LeadStatus::REJECTED;

        $lead->forceFill(['status' => $to, 'responded_at' => $at])->saveQuietly();

        LeadFeedback::create([
            'lead_id' => $lead->id,
            'agent_id' => $agent->id,
            'decision' => $decision,
            'reason' => $reason,
            'comments' => $comments,
            'created_at' => $at,
        ]);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => LeadStatus::NEW->value,
            'to_status' => $to->value,
            'changed_by' => $agent->id,
            'created_at' => $at,
        ]);

        if ($decision === 'accept' && $lead->lead_fee_cents > 0) {
            Transaction::create([
                'agent_id' => $agent->id,
                'lead_id' => $lead->id,
                'type' => TransactionType::LEAD_FEE,
                'amount_cents' => $lead->lead_fee_cents,
                'currency' => 'USD',
                'status' => 'completed',
                'reference' => 'FEE-'.$lead->reference_id,
                'created_at' => $at,
            ]);
        }

        ActivityLogger::lead("lead.{$decision}ed", $lead->id, ['agent_id' => $agent->id, 'reason' => $reason], $agent);
    }

    private function convert(Lead $lead, User $staff, $at): void
    {
        $lead->forceFill([
            'status' => LeadStatus::CONVERTED,
            'converted_at' => $at,
        ])->saveQuietly();

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => LeadStatus::IN_PROGRESS->value,
            'to_status' => LeadStatus::CONVERTED->value,
            'changed_by' => $staff->id,
            'created_at' => $at,
        ]);

        Notification::create([
            'user_id' => $lead->assigned_to,
            'type' => 'lead_update',
            'title' => 'Lead marked converted',
            'body' => "Congratulations — {$lead->reference_id} ({$lead->name}) was marked converted.",
            'lead_id' => $lead->id,
            'actor_id' => $staff->id,
            'created_at' => $at,
        ]);

        ActivityLogger::lead('lead.status_changed', $lead->id, ['to' => 'converted'], $staff);
    }
}
