<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\FcmService;
use App\Support\MailSettings;
use App\Support\PushSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The three delivery paths the mobile team reported problems with: OTP email,
 * avatar files, and FCM push.
 */
class DeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------- OTP email honesty

    public function test_register_start_reports_failure_instead_of_claiming_success(): void
    {
        // Point at a host that cannot resolve so the transport genuinely fails.
        Setting::set('mail_transport', 'smtp');
        Setting::set('mail_host', 'smtp.invalid-host-for-testing.example');
        Setting::set('mail_port', '2525');
        Setting::set('mail_from_address', 'info@openreferral.us');
        MailSettings::apply();

        $this->postJson('/api/auth/register/start', ['email' => 'newagent@example.com'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'mail_not_configured');

        // No usable code is left behind for an email that never went out.
        $code = VerificationCode::where('email', 'newagent@example.com')->firstOrFail();
        $this->assertNotNull($code->consumed_at);

        $this->assertDatabaseHas('email_log', [
            'to_email' => 'newagent@example.com',
            'event' => 'otp_signup',
            'status' => 'failed',
        ]);
    }

    public function test_the_otp_is_sent_inline_so_it_does_not_need_a_queue_worker(): void
    {
        Mail::fake();
        config()->set('queue.default', 'database'); // a worker would normally be required

        $this->postJson('/api/auth/register/start', ['email' => 'inline@example.com'])->assertOk();

        Mail::assertSent(\App\Mail\OtpCodeMail::class);

        // Sent, not left sitting in the queue.
        $this->assertDatabaseHas('email_log', [
            'to_email' => 'inline@example.com',
            'event' => 'otp_signup',
            'status' => 'sent',
        ]);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count());
    }

    public function test_password_reset_codes_are_also_sent_inline(): void
    {
        Mail::fake();

        $user = User::factory()->agent()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertOk();

        $this->assertDatabaseHas('email_log', [
            'to_email' => 'reset@example.com',
            'event' => 'otp_password_reset',
            'status' => 'sent',
        ]);
    }

    // ------------------------------------------------------ Avatar serving

    public function test_an_uploaded_avatar_is_reachable_over_http(): void
    {
        $agent = User::factory()->agent()->create();

        Sanctum::actingAs($agent);

        $url = $this->postJson('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])->assertOk()->json('avatar_url');

        $this->assertStringContainsString('/storage/avatars/', $url);

        // Fetch it the way the app does — this exercises the PHP fallback route,
        // which is what saves the deployment when public/storage is missing.
        $path = parse_url($url, PHP_URL_PATH);

        $this->get($path)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_the_private_disk_is_not_exposed_over_http(): void
    {
        Storage::disk('local')->put('secret.txt', 'not for the public');

        // /storage/* must resolve against the public disk only.
        $this->get('/storage/secret.txt')->assertNotFound();
    }

    // ---------------------------------------------------------- FCM push

    public function test_a_lead_assignment_pushes_the_payload_the_app_expects(): void
    {
        Mail::fake();
        Http::fake(['fcm.googleapis.com/*' => Http::response(['success' => 1, 'results' => []], 200)]);

        PushSettings::storeServerKey('legacy-test-key');

        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->create();
        $agent->devices()->create(['token' => 'fcm-token-1', 'platform' => 'android']);

        $lead = Lead::factory()->unassigned()->create(['name' => 'Melad Yousef', 'location' => 'Springfield, VA']);

        $this->actingAs($staff)
            ->post("/admin/leads/{$lead->id}/assign", ['agent_id' => $agent->id])
            ->assertRedirect();

        Http::assertSent(function ($request) use ($lead) {
            $data = $request->data()['data'] ?? [];

            return str_contains($request->url(), 'fcm.googleapis.com')
                && ($data['type'] ?? null) === 'lead_assigned'
                && ($data['notification_type'] ?? null) === NotificationType::NEW_LEAD->value
                && ($data['lead_id'] ?? null) === (string) $lead->id
                && ($data['reference_id'] ?? null) === $lead->reference_id;
        });
    }

    public function test_push_is_skipped_for_agents_who_turned_it_off(): void
    {
        Mail::fake();
        Http::fake();

        PushSettings::storeServerKey('legacy-test-key');

        $staff = User::factory()->staff()->create();
        $agent = User::factory()->agent()->create();
        $agent->devices()->create(['token' => 'fcm-token-2', 'platform' => 'ios']);
        $agent->settingsOrDefault()->update(['push_notifications' => false]);

        $lead = Lead::factory()->unassigned()->create();

        $this->actingAs($staff)->post("/admin/leads/{$lead->id}/assign", ['agent_id' => $agent->id]);

        Http::assertNothingSent();

        // The in-app notification is still created.
        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'type' => 'new_lead']);
    }

    public function test_firebase_credentials_can_be_pasted_in_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $serviceAccount = json_encode([
            'type' => 'service_account',
            'project_id' => 'open-referral-test',
            'client_email' => 'firebase-adminsdk@open-referral-test.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIfake\n-----END PRIVATE KEY-----\n",
        ]);

        $this->assertFalse(app(FcmService::class)->isConfigured());

        $this->actingAs($admin)
            ->post('/admin/settings/push', ['service_account_json' => $serviceAccount])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(app(FcmService::class)->isConfigured());
        $this->assertSame('open-referral-test', PushSettings::projectId());

        // Stored encrypted, not as readable JSON.
        $raw = Setting::where('key', PushSettings::KEY_SERVICE_ACCOUNT)->value('value');
        $this->assertStringNotContainsString('private_key', $raw);
        $this->assertStringContainsString('open-referral-test', Crypt::decryptString($raw));
    }

    public function test_the_wrong_firebase_file_is_rejected_with_a_helpful_message(): void
    {
        $admin = User::factory()->admin()->create();

        // google-services.json is the client config, not a service account key.
        $this->actingAs($admin)->post('/admin/settings/push', [
            'service_account_json' => json_encode(['project_info' => ['project_id' => 'x']]),
        ])->assertSessionHas('error');

        $this->actingAs($admin)->post('/admin/settings/push', [
            'service_account_json' => 'this is not json',
        ])->assertSessionHas('error');

        $this->assertFalse(PushSettings::isConfigured());
    }

    public function test_the_settings_page_reports_storage_and_push_health(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('Push notifications (Firebase)')
            ->assertSee('Uploaded files (avatars)')
            ->assertSee('PHP fallback route');
    }

    public function test_staff_cannot_change_push_credentials(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post('/admin/settings/push', ['server_key' => 'nope'])->assertForbidden();

        $this->assertFalse(PushSettings::isConfigured());
    }
}
