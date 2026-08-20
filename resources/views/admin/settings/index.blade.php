<x-layouts.app title="Settings">
    <x-ui.page-header
        title="Settings"
        subtitle="Email delivery, operational defaults, and notification health." />

    {{-- ================================================== Email delivery --}}
    <div id="email-delivery" class="or-card" style="margin-bottom: 22px; border-left: 4px solid {{ $mailIsLive ? 'var(--or-success)' : 'var(--or-danger)' }};">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 6px;">
            <div>
                <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Email delivery</h2>
                <p style="font-size: 13px; color: var(--or-slate); margin: 5px 0 0;">
                    Verification codes, password resets, approvals and lead alerts all go out from here.
                </p>
            </div>
            <span class="or-badge {{ $mailIsLive ? 'badge-green' : 'badge-red' }}" style="padding: 7px 14px;">
                {{ $mailIsLive ? '✓ Live — emails are delivered' : '⚠ Not delivering — emails only go to the log' }}
            </span>
        </div>

        @if(! $mailIsLive)
            <div style="margin: 14px 0 4px; padding: 14px 16px; background-color: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 8px; font-size: 13px; color: #991B1B; line-height: 1.7;">
                <strong>Agents cannot sign up right now.</strong>
                The signup flow emails a 6-digit code — with delivery switched off that code is written to
                <code>storage/logs/laravel.log</code> and never reaches them. Fill in the SMTP details below and
                send yourself a test to switch it on.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.mail') }}" style="margin-top: 18px;">
            @csrf

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0 22px;">
                <div>
                    <x-ui.field
                        label="How should email be sent?" name="mail_transport" required
                        :value="$mail['mail_transport']"
                        :options="[
                            'smtp' => 'SMTP — deliver for real (recommended)',
                            'log' => 'Log only — write to the log, deliver nothing',
                            'env' => 'Use the server .env file',
                        ]"
                        :hint="'Currently sending via: '.$effectiveMailer.($mail['mail_transport'] === 'env' ? ' (from .env)' : '')" />

                    <div style="margin-bottom: 16px;">
                        <label for="mail_preset" style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                            Provider preset
                        </label>
                        <select id="mail_preset"
                                style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background-color: white;">
                            <option value="">— Fill the fields for me —</option>
                            @foreach($mailPresets as $key => $preset)
                                <option value="{{ $key }}"
                                        data-host="{{ $preset['host'] }}"
                                        data-port="{{ $preset['port'] }}"
                                        data-encryption="{{ $preset['encryption'] }}"
                                        data-hint="{{ $preset['hint'] }}">{{ $preset['label'] }}</option>
                            @endforeach
                        </select>
                        <div id="preset_hint" style="display: none; margin-top: 8px; padding: 10px 12px; background-color: var(--or-blue-soft); border-radius: 8px; font-size: 12px; color: #0E5FA8; line-height: 1.6;"></div>
                    </div>

                    <x-ui.field label="SMTP server (host)" name="mail_host" :value="$mail['mail_host']"
                                placeholder="smtp.gmail.com" />

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <x-ui.field label="Port" name="mail_port" type="number" :value="$mail['mail_port']" placeholder="587" />
                        <x-ui.field label="Encryption" name="mail_encryption" required :value="$mail['mail_encryption']"
                                    :options="['tls' => 'TLS / STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None']" />
                    </div>
                </div>

                <div>
                    <x-ui.field label="SMTP username" name="mail_username" :value="$mail['mail_username']"
                                placeholder="you@yourdomain.com"
                                hint="Usually the full mailbox address. SendGrid uses the literal word apikey." />

                    <div style="margin-bottom: 16px;">
                        <label for="mail_password" style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                            SMTP password
                        </label>
                        <input id="mail_password" type="password" name="mail_password" autocomplete="new-password"
                               placeholder="{{ $mailHasPassword ? '•••••••• (saved — leave blank to keep it)' : 'App password or API key' }}"
                               style="width: 100%; padding: 9px 12px; border: 1px solid {{ $errors->has('mail_password') ? 'var(--or-danger)' : '#CBD5E1' }}; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        @error('mail_password')
                            <div style="color: var(--or-danger); font-size: 12px; margin-top: 4px;">{{ $message }}</div>
                        @enderror
                        <div style="color: var(--or-slate); font-size: 12px; margin-top: 4px; line-height: 1.6;">
                            Stored encrypted and never shown again.
                            @if($mailHasPassword)
                                <label style="display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; cursor: pointer;">
                                    <input type="checkbox" name="clear_password" value="1"> Remove the saved password
                                </label>
                            @endif
                        </div>
                    </div>

                    <x-ui.field label="From address" name="mail_from_address" type="email" :value="$mail['mail_from_address']"
                                placeholder="info@openreferral.us"
                                hint="What agents see as the sender. Most providers require this to match the authenticated mailbox or a verified domain." />

                    <x-ui.field label="From name" name="mail_from_name" :value="$mail['mail_from_name']"
                                placeholder="Open Referral" />
                </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 4px;">
                <button type="submit" class="or-btn or-btn-primary" style="padding: 10px 24px;">Save email settings</button>
                <span style="font-size: 12px; color: var(--or-slate);">Save first, then send a test below.</span>
            </div>
        </form>

        {{-- Test send --}}
        <div style="margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--or-line);">
            <h3 style="font-size: 14px; font-weight: 700; margin: 0 0 4px;">Send a test email</h3>
            <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 12px; line-height: 1.6;">
                Sends immediately (not queued) and reports the exact error if your provider rejects it.
            </p>
            <form method="POST" action="{{ route('admin.settings.test-email') }}" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                @csrf
                <input type="email" name="test_email" required value="{{ old('test_email', auth()->user()->email) }}"
                       placeholder="you@example.com"
                       style="flex: 1; min-width: 240px; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                <button type="submit" class="or-btn or-btn-secondary" style="padding: 10px 22px;">Send test</button>
            </form>
        </div>

        {{-- Step-by-step --}}
        <details style="margin-top: 18px; border-top: 1px solid var(--or-line); padding-top: 16px;">
            <summary style="cursor: pointer; font-size: 14px; font-weight: 700; color: var(--or-blue);">
                How do I get these details? (step by step)
            </summary>

            <div style="margin-top: 14px; font-size: 13px; line-height: 1.75; color: var(--or-ink);">
                <p style="margin: 0 0 14px;">
                    You need an SMTP mailbox from one of three places: your email provider (Gmail, Outlook),
                    your web host (cPanel, Hostinger), or a dedicated sending service (SendGrid, Mailgun,
                    Postmark, Brevo). Pick one and follow its steps.
                </p>

                <div style="padding: 14px 16px; background-color: var(--or-paper); border-radius: 8px; margin-bottom: 12px;">
                    <strong>Gmail / Google Workspace</strong>
                    <ol style="margin: 8px 0 0; padding-left: 20px;">
                        <li>Turn on 2-Step Verification at <code>myaccount.google.com/security</code> — App Passwords need it.</li>
                        <li>Open <code>myaccount.google.com/apppasswords</code>, create one named "Open Referral", and copy the 16-character code.</li>
                        <li>Above, pick the <em>Gmail</em> preset, put your full Gmail address in username and from address, and paste the app password.</li>
                        <li>Save, then send a test.</li>
                    </ol>
                    <div style="margin-top: 8px; color: var(--or-slate);">
                        Free Gmail allows roughly 500 emails a day; Workspace around 2,000. Fine to start, not for bulk.
                    </div>
                </div>

                <div style="padding: 14px 16px; background-color: var(--or-paper); border-radius: 8px; margin-bottom: 12px;">
                    <strong>cPanel / Hostinger / your own domain</strong>
                    <ol style="margin: 8px 0 0; padding-left: 20px;">
                        <li>In your hosting panel open <em>Email Accounts</em> and create a mailbox such as <code>info@yourdomain.com</code>.</li>
                        <li>Open that mailbox's <em>Connect Devices</em> / <em>Configuration</em> page and copy the outgoing (SMTP) server, port and encryption.</li>
                        <li>Enter them above. Username is the full mailbox address, password is the one you set for it.</li>
                        <li>Save, then send a test.</li>
                    </ol>
                    <div style="margin-top: 8px; color: var(--or-slate);">
                        This gives the best deliverability because the from address matches your domain.
                    </div>
                </div>

                <div style="padding: 14px 16px; background-color: var(--or-paper); border-radius: 8px; margin-bottom: 12px;">
                    <strong>SendGrid / Mailgun / Postmark / Brevo</strong>
                    <ol style="margin: 8px 0 0; padding-left: 20px;">
                        <li>Create a free account and verify your sending domain (or at least a single sender address).</li>
                        <li>Generate an API key or SMTP credentials in their dashboard.</li>
                        <li>Pick the matching preset above and paste the credentials — each preset tells you what goes in the username field.</li>
                        <li>Save, then send a test.</li>
                    </ol>
                    <div style="margin-top: 8px; color: var(--or-slate);">
                        Best choice once volume grows — they handle reputation, bounces and analytics.
                    </div>
                </div>

                <div style="padding: 14px 16px; background-color: #FEF3C7; border-radius: 8px;">
                    <strong>If the test fails</strong>
                    <ul style="margin: 8px 0 0; padding-left: 20px;">
                        <li><em>Authentication failed</em> — wrong username or password. Gmail and Outlook need an app password, not the account password.</li>
                        <li><em>Connection timed out</em> — the port is blocked or wrong. Try 587 with TLS, or 465 with SSL. Some hosts block outbound SMTP entirely; ask them to open it.</li>
                        <li><em>Sender address rejected</em> — the from address must match the authenticated mailbox or a domain you verified with the provider.</li>
                        <li><em>Certificate problems</em> — on local XAMPP, TLS verification can fail. It normally works on the live server; test there before changing anything.</li>
                        <li>Every attempt is recorded in the delivery log at the bottom of this page with the provider's exact message.</li>
                    </ul>
                </div>
            </div>
        </details>
    </div>

    {{-- =============================================== Push notifications --}}
    <div id="push" class="or-card" style="margin-bottom: 22px; border-left: 4px solid {{ $pushConfigured ? 'var(--or-success)' : 'var(--or-warning)' }};">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
            <div>
                <h2 style="font-size: 16px; font-weight: 700; margin: 0;">Push notifications (Firebase)</h2>
                <p style="font-size: 13px; color: var(--or-slate); margin: 5px 0 0;">
                    Banner alerts on the agent's phone when a lead is assigned or updated.
                    In-app notifications work without this.
                </p>
            </div>
            <span class="or-badge {{ $pushConfigured ? 'badge-green' : 'badge-amber' }}" style="padding: 7px 14px;">
                {{ $pushConfigured ? '✓ Configured'.($pushProjectId ? ' — '.$pushProjectId : '') : 'Not configured — push is off' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.settings.push') }}" style="margin-top: 18px;">
            @csrf

            <label for="service_account_json" style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                Firebase service-account JSON
            </label>
            <textarea id="service_account_json" name="service_account_json" rows="5"
                      placeholder='{ "type": "service_account", "project_id": "open-referral", "private_key": "-----BEGIN PRIVATE KEY-----...", "client_email": "firebase-adminsdk-xxxxx@open-referral.iam.gserviceaccount.com", ... }'
                      style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 12px; font-family: ui-monospace, Menlo, Consolas, monospace; box-sizing: border-box; resize: vertical;"></textarea>
            <div style="color: var(--or-slate); font-size: 12px; margin-top: 4px; line-height: 1.6;">
                Paste the whole file. Stored encrypted; never shown again.
                @if($pushFromDashboard)
                    <label style="display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; cursor: pointer;">
                        <input type="checkbox" name="clear" value="1"> Remove the saved credentials
                    </label>
                @endif
            </div>

            <div style="margin-top: 14px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <button type="submit" class="or-btn or-btn-primary" style="padding: 10px 24px;">Save Firebase credentials</button>
                <span style="font-size: 12px; color: var(--or-slate);">
                    Takes effect immediately — no redeploy.
                </span>
            </div>
        </form>

        <details style="margin-top: 18px; border-top: 1px solid var(--or-line); padding-top: 16px;">
            <summary style="cursor: pointer; font-size: 14px; font-weight: 700; color: var(--or-blue);">
                How to set up Firebase (and what the app team needs)
            </summary>

            <div style="margin-top: 14px; font-size: 13px; line-height: 1.75;">
                <div style="padding: 14px 16px; background-color: var(--or-paper); border-radius: 8px; margin-bottom: 12px;">
                    <strong>1. Create the project</strong>
                    <ol style="margin: 8px 0 0; padding-left: 20px;">
                        <li>Go to <code>console.firebase.google.com</code> and create a project called "Open Referral".</li>
                        <li>Add an <strong>Android</strong> app — the package name must match the Flutter app's
                            <code>applicationId</code>; ask the app team for it. Download <code>google-services.json</code>.</li>
                        <li>Add an <strong>iOS</strong> app with the app's bundle ID. Download <code>GoogleService-Info.plist</code>.</li>
                        <li>For iOS push you also need an APNs key: Apple Developer → Keys → create a key with
                            "Apple Push Notifications service" enabled, then upload the <code>.p8</code> in
                            Firebase → Project settings → Cloud Messaging.</li>
                    </ol>
                </div>

                <div style="padding: 14px 16px; background-color: var(--or-paper); border-radius: 8px; margin-bottom: 12px;">
                    <strong>2. Give this backend its key</strong>
                    <ol style="margin: 8px 0 0; padding-left: 20px;">
                        <li>Firebase → Project settings → <em>Service accounts</em> → <strong>Generate new private key</strong>.</li>
                        <li>Open the downloaded <code>.json</code> in a text editor, copy everything, and paste it into the box above.</li>
                        <li>Save. The badge turns green and shows your project id.</li>
                    </ol>
                    <div style="margin-top: 8px; color: var(--or-slate);">
                        This is the <em>service account</em> key, not <code>google-services.json</code> —
                        that one is for the mobile app, and this box will reject it.
                    </div>
                </div>

                <div style="padding: 14px 16px; background-color: var(--or-blue-soft); border-radius: 8px;">
                    <strong>3. Send the app team</strong>
                    <ul style="margin: 8px 0 0; padding-left: 20px;">
                        <li><code>google-services.json</code> (Android) and <code>GoogleService-Info.plist</code> (iOS).</li>
                        <li>An invite to the Firebase project so they can manage the client side.</li>
                    </ul>
                    <div style="margin-top: 8px; color: #0E5FA8;">
                        They do not need the service-account key — keep that on the server only.
                    </div>
                </div>
            </div>
        </details>
    </div>

    {{-- ================================================= File storage =======--}}
    <div class="or-card" style="margin-bottom: 22px; border-left: 4px solid {{ $storage['link_exists'] || $storage['served_by_php'] ? 'var(--or-success)' : 'var(--or-danger)' }};">
        <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Uploaded files (avatars)</h2>
        <p style="font-size: 13px; color: var(--or-slate); margin: 0 0 14px;">
            Profile photos live in <code>storage/app/public</code> and are served from
            <code>{{ $storage['base_url'] }}</code>.
        </p>

        <table style="width: 100%; font-size: 13px; margin-bottom: 12px;">
            <tr>
                <td style="color: var(--or-slate); padding: 7px 0;">Public symlink</td>
                <td style="text-align: right;">
                    <span class="or-badge {{ $storage['link_exists'] ? 'badge-green' : 'badge-amber' }}">
                        {{ $storage['link_exists'] ? 'Present' : 'Missing' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="color: var(--or-slate); padding: 7px 0;">PHP fallback route</td>
                <td style="text-align: right;">
                    <span class="or-badge {{ $storage['served_by_php'] ? 'badge-green' : 'badge-red' }}">
                        {{ $storage['served_by_php'] ? 'Enabled' : 'Disabled' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="color: var(--or-slate); padding: 7px 0;">Storage writable</td>
                <td style="text-align: right;">
                    <span class="or-badge {{ $storage['writable'] ? 'badge-green' : 'badge-red' }}">
                        {{ $storage['writable'] ? 'Yes' : 'No' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="color: var(--or-slate); padding: 7px 0;">Avatars stored</td>
                <td style="text-align: right;"><strong>{{ $storage['avatar_count'] }}</strong></td>
            </tr>
        </table>

        @if(! $storage['link_exists'])
            <div style="padding: 12px 14px; background-color: #FEF3C7; border-radius: 8px; font-size: 12px; color: #92400E; line-height: 1.7;">
                <strong>The symlink is missing.</strong>
                Files are still served by PHP (slower, but working). To restore the fast path, run on the server:
                <div style="margin-top: 6px;"><code>php artisan storage:link</code></div>
                If that fails because <code>public_html</code> is itself a symlink, create it by hand from the
                project root: <code>ln -s ../storage/app/public public/storage</code>
            </div>
        @endif
    </div>

    {{-- ============================================ General + health =========--}}
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

            <h2 style="font-size: 15px; font-weight: 700; margin: 22px 0 14px 0;">Verification codes (OTP)</h2>

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

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Delivery status</h2>

            <table style="width: 100%; font-size: 13px;">
                <tr>
                    <td style="color: var(--or-slate); padding: 7px 0;">Email</td>
                    <td style="text-align: right;">
                        <span class="or-badge {{ $mailIsLive ? 'badge-green' : 'badge-red' }}">
                            {{ $mailIsLive ? 'Delivering ('.$effectiveMailer.')' : 'Log only' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="color: var(--or-slate); padding: 7px 0;">Push (FCM)</td>
                    <td style="text-align: right;">
                        <span class="or-badge {{ $pushConfigured ? 'badge-green' : 'badge-amber' }}">
                            {{ $pushConfigured ? 'Configured' : 'Not configured' }}
                        </span>
                    </td>
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
                    <strong>Push is off.</strong> Paste your Firebase service-account JSON in the
                    <a href="#push" style="color: #92400E; font-weight: 700;">push section above</a>.
                    In-app notifications and emails still work without it.
                </div>
            @endif

            @if($queue === 'sync')
                <div style="margin-top: 12px; padding: 12px 14px; background-color: var(--or-blue-soft); border-radius: 8px; font-size: 12px; color: #0E5FA8; line-height: 1.6;">
                    Queue is <code>sync</code>, so emails send inline and slow requests down.
                    Set <code>QUEUE_CONNECTION=database</code> and run <code>php artisan queue:work</code> in production.
                </div>
            @else
                <div style="margin-top: 12px; padding: 12px 14px; background-color: var(--or-paper); border-radius: 8px; font-size: 12px; color: var(--or-slate); line-height: 1.6;">
                    Emails are queued. A worker must be running — <code>php artisan queue:work</code> — or nothing
                    leaves the queue. Saving email settings automatically signals workers to reload.
                </div>
            @endif
        </div>
    </div>

    {{-- ==================================================== Delivery log --}}
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
                                        <div style="font-size: 11px; color: var(--or-danger); margin-top: 3px; max-width: 320px;">{{ Str::limit($email->error, 160) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
        (function () {
            const preset = document.getElementById('mail_preset');
            const hint = document.getElementById('preset_hint');
            if (!preset) return;

            preset.addEventListener('change', () => {
                const option = preset.selectedOptions[0];
                if (!option || !option.value) {
                    hint.style.display = 'none';
                    return;
                }

                const set = (name, value) => {
                    const field = document.querySelector(`[name="${name}"]`);
                    if (field && value !== '') field.value = value;
                };

                set('mail_host', option.dataset.host);
                set('mail_port', option.dataset.port);
                set('mail_encryption', option.dataset.encryption);

                // Presets only make sense when actually sending over SMTP.
                const transport = document.querySelector('[name="mail_transport"]');
                if (transport) transport.value = 'smtp';

                hint.textContent = option.dataset.hint;
                hint.style.display = 'block';
            });
        })();
    </script>
</x-layouts.app>
