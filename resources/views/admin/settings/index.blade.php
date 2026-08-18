<x-layouts.app title="Settings">
    <x-ui.page-header
        title="Settings"
        subtitle="Operational defaults, notification delivery health, and OTP policy." />

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px; margin-bottom: 22px;">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="or-card">
            @csrf
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">General</h2>

            <x-ui.field label="Company name" name="company_name" :value="$settings['company_name']" required
                        hint="Used in emails and the dashboard title." />
            <x-ui.field label="Support email" name="support_email" type="email" :value="$settings['support_email']" required />
            <x-ui.field label="Default lead fee ($)" name="default_lead_fee_dollars" type="number" step="0.01"
                        :value="$settings['default_lead_fee_dollars']" required
                        hint="Pre-filled when staff create a lead; billed when an agent accepts." />

            <h2 style="font-size: 15px; font-weight: 700; margin: 22px 0 14px 0;">OTP policy</h2>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <x-ui.field label="Code lifetime (min)" name="otp_ttl_minutes" type="number"
                            :value="$settings['otp_ttl_minutes']" required />
                <x-ui.field label="Max attempts" name="otp_max_attempts" type="number"
                            :value="$settings['otp_max_attempts']" required />
            </div>
            <x-ui.field label="Resend cooldown (seconds)" name="otp_resend_seconds" type="number"
                        :value="$settings['otp_resend_seconds']" required
                        hint="The app shows a countdown of this length on its resend button." />

            <button type="submit" class="or-btn or-btn-primary" style="width: 100%; margin-top: 6px;">Save settings</button>
        </form>

        <div style="display: flex; flex-direction: column; gap: 18px;">
            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Delivery status</h2>

                <table style="width: 100%; font-size: 13px;">
                    <tr>
                        <td style="color: var(--or-slate); padding: 7px 0;">Push (FCM)</td>
                        <td style="text-align: right;">
                            <span class="or-badge {{ $pushConfigured ? 'badge-green' : 'badge-amber' }}">
                                {{ $pushConfigured ? 'Configured' : 'Not configured' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: var(--or-slate); padding: 7px 0;">Mail transport</td>
                        <td style="text-align: right;"><code>{{ $mailer }}</code></td>
                    </tr>
                    <tr>
                        <td style="color: var(--or-slate); padding: 7px 0;">Queue driver</td>
                        <td style="text-align: right;"><code>{{ $queue }}</code></td>
                    </tr>
                    @foreach(['sent' => 'badge-green', 'queued' => 'badge-amber', 'failed' => 'badge-red'] as $status => $class)
                        <tr>
                            <td style="color: var(--or-slate); padding: 7px 0;">Emails {{ $status }}</td>
                            <td style="text-align: right;">
                                <span class="or-badge {{ $class }}">{{ number_format($emailStats[$status] ?? 0) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </table>

                @if(! $pushConfigured)
                    <div style="margin-top: 14px; padding: 12px 14px; background-color: #FEF3C7; border-radius: 8px; font-size: 12px; color: #92400E; line-height: 1.6;">
                        <strong>Push is disabled.</strong> Set <code>FCM_CREDENTIALS</code> to a Firebase
                        service-account JSON path (or <code>FCM_SERVER_KEY</code> for the legacy API) in
                        <code>.env</code>. In-app notifications and emails still work without it.
                    </div>
                @endif

                @if($queue === 'sync')
                    <div style="margin-top: 12px; padding: 12px 14px; background-color: #E8F3FC; border-radius: 8px; font-size: 12px; color: #0E5FA8; line-height: 1.6;">
                        Queue is <code>sync</code>, so emails send inline and slow requests down.
                        Set <code>QUEUE_CONNECTION=database</code> and run <code>php artisan queue:work</code> in production.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <h2 style="font-size: 15px; font-weight: 700; margin: 0; padding: 18px 20px 12px;">Recent emails</h2>
        @if($recentEmails->isEmpty())
            <x-ui.empty icon="✉️" title="No emails sent yet" />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead><tr><th>When</th><th>Event</th><th>To</th><th>Subject</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($recentEmails as $email)
                            <tr>
                                <td style="font-size: 12px; color: var(--or-slate); white-space: nowrap;">{{ $email->created_at?->format('M j, g:i A') }}</td>
                                <td style="font-size: 12px;"><code>{{ $email->event }}</code></td>
                                <td style="font-size: 13px;">{{ $email->to_email }}</td>
                                <td style="font-size: 13px; color: var(--or-slate); max-width: 320px;">{{ $email->subject }}</td>
                                <td>
                                    @php($classes = ['sent' => 'badge-green', 'queued' => 'badge-amber', 'failed' => 'badge-red'])
                                    <span class="or-badge {{ $classes[$email->status] ?? 'badge-slate' }}">{{ ucfirst($email->status) }}</span>
                                    @if($email->error)
                                        <div style="font-size: 11px; color: var(--or-danger); margin-top: 3px;">{{ Str::limit($email->error, 80) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
