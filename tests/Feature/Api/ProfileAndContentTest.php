<?php

namespace Tests\Feature\Api;

use App\Models\ContentPage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAndContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_documented_profile_shape(): void
    {
        $agent = User::factory()->agent()->create(['phone' => '+1 305 555 0100']);
        $agent->agentProfile->update(['brokerage' => 'Premier South', 'verified' => true]);
        $agent->serviceAreas()->create(['area' => 'Miami, FL']);

        Sanctum::actingAs($agent);

        $this->getJson('/api/me')->assertOk()->assertJson([
            'data' => [
                'id' => $agent->id,
                'email' => $agent->email,
                'status' => 'active',
                'verified' => true,
                'brokerage' => 'Premier South',
                'service_areas' => ['Miami, FL'],
                'settings' => ['push_notifications' => true, 'email_notifications' => true, 'language' => 'en'],
            ],
        ]);
    }

    public function test_profile_update_replaces_service_areas_and_ignores_email(): void
    {
        $agent = User::factory()->agent()->create(['email' => 'fixed@example.com']);
        $agent->serviceAreas()->create(['area' => 'Old Area, FL']);

        Sanctum::actingAs($agent);

        $this->putJson('/api/me', [
            'name' => 'Updated Name',
            'phone' => '+1 407 555 0199',
            'service_areas' => ['Orlando, FL', 'Winter Park, FL', 'Orlando, FL'],
            'license_number' => 'FL-1234',
            'license_state' => 'FL',
            'brokerage' => 'New Brokerage',
            'bio' => 'Updated bio.',
            'email' => 'hacker@example.com',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Name');

        $agent->refresh();
        $this->assertSame('fixed@example.com', $agent->email, 'Email must not be editable via /me.');
        $this->assertSame('New Brokerage', $agent->agentProfile->brokerage);

        // Duplicates collapse, and the old area is gone.
        $this->assertEqualsCanonicalizing(
            ['Orlando, FL', 'Winter Park, FL'],
            $agent->serviceAreas()->pluck('area')->all()
        );
    }

    public function test_avatar_upload_stores_the_file_and_returns_a_url(): void
    {
        Storage::fake('public');

        $agent = User::factory()->agent()->create();

        Sanctum::actingAs($agent);

        $response = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ])->assertOk();

        $this->assertNotEmpty($response->json('avatar_url'));

        $stored = $agent->fresh()->rawAvatar();
        $this->assertNotNull($stored);
        Storage::disk('public')->assertExists($stored);

        $this->postJson('/api/me/avatar', ['avatar' => UploadedFile::fake()->create('doc.pdf', 10)])
            ->assertStatus(422);
    }

    public function test_change_password_verifies_the_current_one_and_keeps_this_session(): void
    {
        $agent = User::factory()->agent()->create(['password' => 'oldpassword']);
        $token = $agent->createToken('mobile_agent')->plainTextToken;
        $agent->createToken('another_device');

        $this->withToken($token)->postJson('/api/me/change-password', [
            'current_password' => 'wrong',
            'new_password' => 'newpassword',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->withToken($token)->postJson('/api/me/change-password', [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpassword', $agent->fresh()->password));

        // This token survives; the other device is signed out.
        $this->assertSame(1, $agent->fresh()->tokens()->count());
        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_settings_can_be_read_and_updated(): void
    {
        $agent = User::factory()->agent()->create();

        Sanctum::actingAs($agent);

        $this->getJson('/api/me/settings')->assertOk()
            ->assertJsonPath('data.push_notifications', true);

        $this->putJson('/api/me/settings', [
            'push_notifications' => false,
            'email_notifications' => false,
            'language' => 'es',
            'theme' => 'dark',
        ])->assertOk()->assertJson([
            'data' => [
                'push_notifications' => false,
                'email_notifications' => false,
                'language' => 'es',
                'theme' => 'dark',
            ],
        ]);

        $this->putJson('/api/me/settings', ['theme' => 'neon'])->assertStatus(422);
    }

    public function test_account_deletion_soft_deletes_and_releases_open_leads(): void
    {
        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->create();

        $open = Lead::factory()->assignedTo($agent->id, $staff->id)->create();
        $converted = Lead::factory()->assignedTo($agent->id, $staff->id)->create(['status' => 'converted']);

        Sanctum::actingAs($agent);

        $this->deleteJson('/api/me')->assertOk();

        $this->assertSoftDeleted('users', ['id' => $agent->id]);
        $this->assertSame(0, $agent->tokens()->count());

        // The open lead goes back to the pool; converted history stays attached.
        $this->assertNull($open->fresh()->assigned_to);
        $this->assertSame($agent->id, $converted->fresh()->assigned_to);
    }

    public function test_content_pages_are_public_and_shaped_for_the_app(): void
    {
        ContentPage::create([
            'slug' => 'about',
            'title' => 'About Open Referral',
            'intro' => 'A referral network.',
            'body_json' => [['heading' => 'What we do', 'body' => 'We route leads.']],
        ]);

        $this->getJson('/api/content/about')->assertOk()->assertJson([
            'slug' => 'about',
            'title' => 'About Open Referral',
            'intro' => 'A referral network.',
            'sections' => [['heading' => 'What we do', 'body' => 'We route leads.']],
        ]);

        $this->getJson('/api/content')->assertOk()->assertJsonCount(1, 'data');

        // A valid slug with nothing published, and an invalid slug, both 404.
        $this->getJson('/api/content/terms')->assertNotFound();
        $this->getJson('/api/content/not-a-page')->assertNotFound();
    }
}
