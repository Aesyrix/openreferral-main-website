<?php

namespace Tests\Feature\Dashboard;

use App\Mail\TestMail;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSetupTest extends TestCase
{
    use RefreshDatabase;

    private function smtpPayload(array $overrides = []): array
    {
        return array_merge([
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_username' => 'info@openreferral.us',
            'mail_password' => 'app-password-1234',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'info@openreferral.us',
            'mail_from_name' => 'Open Referral',
        ], $overrides);
    }

    public function test_admin_can_save_smtp_settings_and_they_reach_the_mail_config(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/mail', $this->smtpPayload())->assertRedirect();

        $this->assertSame('smtp', Setting::get('mail_transport'));
        $this->assertSame('smtp.gmail.com', Setting::get('mail_host'));
        $this->assertSame('587', (string) Setting::get('mail_port'));

        // The live config is overlaid, so the very next send uses these values.
        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('app-password-1234', config('mail.mailers.smtp.password'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('info@openreferral.us', config('mail.from.address'));
        $this->assertSame('Open Referral', config('mail.from.name'));

        $this->assertTrue(MailSettings::isLive());
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.mail_updated']);
    }

    public function test_the_smtp_password_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/mail', $this->smtpPayload())->assertRedirect();

        $stored = Setting::where('key', 'mail_password')->value('value');

        $this->assertNotSame('app-password-1234', $stored, 'The password must not be stored in plain text.');
        $this->assertSame('app-password-1234', Crypt::decryptString($stored));
        $this->assertSame('app-password-1234', MailSettings::password());
    }

    public function test_a_blank_password_keeps_the_saved_one_and_the_checkbox_clears_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/mail', $this->smtpPayload());

        // Re-saving without retyping the password must not wipe it.
        $this->actingAs($admin)->post('/admin/settings/mail', $this->smtpPayload([
            'mail_password' => '',
            'mail_host' => 'smtp.office365.com',
        ]))->assertRedirect();

        $this->assertSame('smtp.office365.com', Setting::get('mail_host'));
        $this->assertSame('app-password-1234', MailSettings::password());

        $this->actingAs($admin)->post('/admin/settings/mail', $this->smtpPayload([
            'mail_password' => '',
            'clear_password' => 1,
        ]))->assertRedirect();

        $this->assertSame('', MailSettings::password());
        $this->assertFalse(MailSettings::hasPassword());
    }

    public function test_ssl_selects_the_smtps_scheme(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/mail', $this->smtpPayload([
            'mail_port' => 465,
            'mail_encryption' => 'ssl',
        ]))->assertRedirect();

        MailSettings::apply();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
    }

    public function test_smtp_requires_host_port_and_from_address(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/mail', [
            'mail_transport' => 'smtp',
            'mail_encryption' => 'tls',
        ])->assertSessionHasErrors(['mail_host', 'mail_port', 'mail_from_address']);

        $this->assertSame('env', Setting::get('mail_transport'));
    }

    public function test_log_transport_forces_the_log_mailer_and_reports_as_not_live(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings/mail', [
            'mail_transport' => 'log',
            'mail_encryption' => 'tls',
        ])->assertRedirect();

        MailSettings::apply();

        $this->assertSame('log', config('mail.default'));
        $this->assertFalse(MailSettings::isLive());
        $this->assertSame('log', MailSettings::effectiveMailer());
    }

    public function test_the_env_transport_leaves_the_config_untouched(): void
    {
        $admin = User::factory()->admin()->create();
        config()->set('mail.default', 'array');

        $this->actingAs($admin)->post('/admin/settings/mail', [
            'mail_transport' => 'env',
            'mail_encryption' => 'tls',
        ])->assertRedirect();

        MailSettings::apply();

        $this->assertSame('array', config('mail.default'), 'env mode must not override the configured mailer.');
    }

    public function test_a_test_email_is_sent_immediately_and_logged(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/settings/test-email', ['test_email' => 'check@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('check@example.com'));

        $this->assertDatabaseHas('email_log', [
            'to_email' => 'check@example.com',
            'event' => 'test',
            'status' => 'sent',
        ]);
    }

    public function test_a_failing_test_email_reports_the_provider_error_instead_of_throwing(): void
    {
        $admin = User::factory()->admin()->create();

        // Point at a host that cannot resolve, so the transport really fails.
        Setting::set('mail_transport', 'smtp');
        Setting::set('mail_host', 'smtp.invalid-host-for-testing.example');
        Setting::set('mail_port', '2525');
        Setting::set('mail_from_address', 'info@openreferral.us');
        MailSettings::apply();

        $response = $this->actingAs($admin)
            ->post('/admin/settings/test-email', ['test_email' => 'check@example.com']);

        $response->assertRedirect();
        $this->assertStringContainsString('Test email failed', session('error'));

        $log = EmailLog::where('event', 'test')->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertNotEmpty($log->error);
    }

    public function test_the_settings_page_shows_setup_help_and_the_delivery_state(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('Email delivery')
            ->assertSee('Send a test email')
            ->assertSee('How do I get these details?', false)
            ->assertSee('Gmail / Google Workspace')
            ->assertSee('smtp.sendgrid.net');
    }

    public function test_the_dashboard_warns_an_admin_when_email_is_not_delivering(): void
    {
        $admin = User::factory()->admin()->create();

        Setting::set('mail_transport', 'log');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('new agents cannot sign up', false);

        Setting::set('mail_transport', 'smtp');
        Setting::set('mail_host', 'smtp.gmail.com');
        Setting::set('mail_port', '587');
        Setting::set('mail_from_address', 'info@openreferral.us');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('new agents cannot sign up', false);
    }

    public function test_staff_cannot_change_email_settings(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post('/admin/settings/mail', $this->smtpPayload())->assertForbidden();
        $this->actingAs($staff)->post('/admin/settings/test-email', ['test_email' => 'x@example.com'])->assertForbidden();

        $this->assertSame('env', Setting::get('mail_transport'));
    }
}
