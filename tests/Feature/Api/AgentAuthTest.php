<?php

namespace Tests\Feature\Api;

use App\Mail\AgentAwaitingApprovalMail;
use App\Mail\OtpCodeMail;
use App\Models\Notification;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AgentAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_runs_start_verify_complete_and_creates_a_pending_agent(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        // 1. Request a code.
        $this->postJson('/api/auth/register/start', ['email' => 'New.Agent@Example.com'])
            ->assertOk()
            ->assertJsonStructure(['message', 'email', 'resend_in', 'expires_in']);

        Mail::assertSent(OtpCodeMail::class);

        $record = VerificationCode::where('email', 'new.agent@example.com')->firstOrFail();
        $this->assertSame(VerificationCode::PURPOSE_SIGNUP, $record->purpose);
        $this->assertNotSame('', $record->code_hash);

        // The raw code is never stored, so read it off the queued mailable.
        $code = $this->capturedOtp();
        $this->assertTrue(Hash::check($code, $record->code_hash));

        // 2. Verify it.
        $token = $this->postJson('/api/auth/register/verify', [
            'email' => 'new.agent@example.com',
            'code' => $code,
        ])->assertOk()->json('verification_token');

        $this->assertNotEmpty($token);

        // 3. Complete signup.
        $response = $this->postJson('/api/auth/register/complete', [
            'email' => 'new.agent@example.com',
            'verification_token' => $token,
            'password' => 'sup3rsecret',
            'name' => 'New Agent',
        ])->assertCreated()->assertJsonPath('agent.status', 'pending');

        $this->assertNotEmpty($response->json('token'));

        $agent = User::where('email', 'new.agent@example.com')->firstOrFail();
        $this->assertTrue($agent->isAgent());
        $this->assertTrue($agent->isPending());
        $this->assertNotNull($agent->agentProfile);
        $this->assertNotNull($agent->settings);

        // Event #1: every staff member and admin is notified, in-app and by email.
        foreach ([$admin, $staff] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'type' => 'agent_signup',
                'actor_id' => $agent->id,
            ]);
        }

        Mail::assertSent(AgentAwaitingApprovalMail::class, 2);
        $this->assertDatabaseHas('email_log', ['event' => 'agent_signup', 'to_email' => $admin->email]);
    }

    public function test_verification_token_cannot_be_reused(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register/start', ['email' => 'once@example.com'])->assertOk();

        $token = $this->postJson('/api/auth/register/verify', [
            'email' => 'once@example.com',
            'code' => $this->capturedOtp(),
        ])->json('verification_token');

        $this->postJson('/api/auth/register/complete', [
            'email' => 'once@example.com',
            'verification_token' => $token,
            'password' => 'sup3rsecret',
            'name' => 'First Try',
        ])->assertCreated();

        // Same token, different email — the account already exists, and the token is spent.
        $this->postJson('/api/auth/register/complete', [
            'email' => 'once@example.com',
            'verification_token' => $token,
            'password' => 'sup3rsecret',
            'name' => 'Second Try',
        ])->assertStatus(422);

        $this->assertSame(1, User::where('email', 'once@example.com')->count());
    }

    public function test_wrong_code_counts_an_attempt_and_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register/start', ['email' => 'attempts@example.com'])->assertOk();

        $this->postJson('/api/auth/register/verify', [
            'email' => 'attempts@example.com',
            'code' => '000000',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertSame(1, VerificationCode::where('email', 'attempts@example.com')->first()->attempts);
    }

    public function test_signup_is_refused_for_an_existing_email(): void
    {
        User::factory()->agent()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register/start', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_resend_is_throttled_by_the_cooldown(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register/start', ['email' => 'throttle@example.com'])->assertOk();

        $this->postJson('/api/auth/register/start', ['email' => 'throttle@example.com'])
            ->assertStatus(429)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_returns_a_token_and_the_agent_profile(): void
    {
        $agent = User::factory()->agent()->create([
            'email' => 'agent@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'agent@example.com',
            'password' => 'password',
            'device_token' => 'device-abc',
            'platform' => 'ios',
        ])->assertOk()
            ->assertJsonPath('agent.id', $agent->id)
            ->assertJsonPath('agent.status', 'active');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('devices', ['token' => 'device-abc', 'user_id' => $agent->id, 'platform' => 'ios']);
        $this->assertNotNull($agent->fresh()->last_login_at);
    }

    public function test_pending_agent_can_log_in_but_is_blocked_from_leads(): void
    {
        $agent = User::factory()->pending()->create(['password' => 'password']);

        $token = $this->postJson('/api/auth/login', [
            'email' => $agent->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('agent.status', 'pending')->json('token');

        // /me stays reachable so the app can render its pending state.
        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->withToken($token)->getJson('/api/leads')
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_not_active')
            ->assertJsonPath('status', 'pending');
    }

    public function test_suspended_agent_cannot_log_in(): void
    {
        $agent = User::factory()->agent()->suspended()->create(['password' => 'password']);

        $this->postJson('/api/auth/login', ['email' => $agent->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_not_active');
    }

    public function test_staff_cannot_log_into_the_mobile_app(): void
    {
        $staff = User::factory()->staff()->create(['password' => 'password']);

        $this->postJson('/api/auth/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'not_an_agent');
    }

    public function test_bad_credentials_are_rejected(): void
    {
        User::factory()->agent()->create(['email' => 'agent@example.com', 'password' => 'password']);

        $this->postJson('/api/auth/login', ['email' => 'agent@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_password_reset_flow_changes_the_password_and_revokes_tokens(): void
    {
        Mail::fake();

        $agent = User::factory()->agent()->create(['email' => 'reset@example.com', 'password' => 'oldpassword']);
        $agent->createToken('mobile_agent');

        $this->postJson('/api/auth/password/forgot', ['email' => 'reset@example.com'])->assertOk();

        $resetToken = $this->postJson('/api/auth/password/verify', [
            'email' => 'reset@example.com',
            'code' => $this->capturedOtp(),
        ])->assertOk()->json('reset_token');

        $this->postJson('/api/auth/password/reset', [
            'email' => 'reset@example.com',
            'reset_token' => $resetToken,
            'password' => 'brandnewpass',
        ])->assertOk();

        $agent->refresh();
        $this->assertTrue(Hash::check('brandnewpass', $agent->password));
        $this->assertSame(0, $agent->tokens()->count());
    }

    public function test_forgot_password_does_not_disclose_unknown_emails(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, we sent it a reset code.');

        Mail::assertNothingSent();
    }

    public function test_logout_revokes_the_current_token_and_device(): void
    {
        $agent = User::factory()->agent()->create();
        $token = $agent->createToken('mobile_agent')->plainTextToken;
        $agent->devices()->create(['token' => 'device-xyz', 'platform' => 'android']);

        $this->withToken($token)->postJson('/api/auth/logout', ['device_token' => 'device-xyz'])->assertOk();

        $this->assertSame(0, $agent->fresh()->tokens()->count());
        $this->assertDatabaseMissing('devices', ['token' => 'device-xyz']);
    }

    public function test_unauthenticated_requests_get_the_documented_error_shape(): void
    {
        $this->getJson('/api/me')->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
    }

    /** Pull the 6-digit code out of the queued OTP mailable. */
    private function capturedOtp(): string
    {
        $code = null;

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->assertNotNull($code, 'No OTP email was sent.');

        return $code;
    }
}
