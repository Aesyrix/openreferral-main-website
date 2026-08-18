<x-layouts.app title="My Profile">
    <x-ui.page-header
        title="My Profile"
        subtitle="This is what our team and clients see. Keep your service areas current so you get the right leads." />

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(330px, 1fr)); gap: 18px;">
        <form method="POST" action="{{ route('agent.profile.update') }}" enctype="multipart/form-data" class="or-card">
            @csrf
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Details</h2>

            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 18px;">
                <img src="{{ $agent->avatar_url }}" alt="" style="width: 62px; height: 62px; border-radius: 50%; object-fit: cover;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">Profile photo</label>
                    <input type="file" name="avatar" accept="image/*" style="font-size: 13px;">
                    @error('avatar')<div style="color: var(--or-danger); font-size: 12px; margin-top: 4px;">{{ $message }}</div>@enderror
                </div>
            </div>

            <x-ui.field label="Full name" name="name" :value="$agent->name" required />
            <x-ui.field label="Phone" name="phone" type="tel" :value="$agent->phone" />

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">Email</label>
                <input type="email" value="{{ $agent->email }}" disabled
                       style="width: 100%; padding: 9px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 14px; box-sizing: border-box; background-color: var(--or-paper); color: var(--or-slate);">
                <div style="color: var(--or-slate); font-size: 12px; margin-top: 4px;">Your email is fixed after signup — contact support to change it.</div>
            </div>

            <x-ui.field label="Brokerage" name="brokerage" :value="$profile?->brokerage" placeholder="Premier South Realty" />

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                <x-ui.field label="License number" name="license_number" :value="$profile?->license_number" />
                <x-ui.field label="State" name="license_state" :value="$profile?->license_state" placeholder="FL" />
            </div>

            <x-ui.field label="Service areas" name="service_areas" type="textarea" :rows="3"
                        :value="$agent->serviceAreas->pluck('area')->join(\", \n\")"
                        hint="One area per line, or comma separated. e.g. Miami, FL" />

            <x-ui.field label="Bio" name="bio" type="textarea" :rows="4" :value="$profile?->bio"
                        placeholder="Tell clients what you specialise in." />

            <button type="submit" class="or-btn or-btn-primary" style="width: 100%;">Save profile</button>
        </form>

        <div style="display: flex; flex-direction: column; gap: 18px;">
            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Account</h2>
                <table style="width: 100%; font-size: 13px;">
                    <tr>
                        <td style="color: var(--or-slate); padding: 7px 0;">Status</td>
                        <td style="text-align: right;"><x-ui.user-status :status="$agent->status" /></td>
                    </tr>
                    <tr>
                        <td style="color: var(--or-slate); padding: 7px 0;">Verified badge</td>
                        <td style="text-align: right;">
                            <span class="or-badge {{ $profile?->verified ? 'badge-blue' : 'badge-slate' }}">
                                {{ $profile?->verified ? '✔ Verified' : 'Not verified' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: var(--or-slate); padding: 7px 0;">Title</td>
                        <td style="text-align: right;">{{ $profile?->title ?? 'Real Estate Agent' }}</td>
                    </tr>
                    <tr>
                        <td style="color: var(--or-slate); padding: 7px 0;">Member since</td>
                        <td style="text-align: right;">{{ $agent->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @if($profile?->approved_at)
                        <tr>
                            <td style="color: var(--or-slate); padding: 7px 0;">Approved</td>
                            <td style="text-align: right;">{{ $profile->approved_at->format('M j, Y') }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            <form method="POST" action="{{ route('agent.profile.settings') }}" class="or-card">
                @csrf
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Notifications</h2>

                <label style="display: flex; align-items: center; gap: 10px; font-size: 13px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" name="push_notifications" value="1" @checked($settings->push_notifications)>
                    Push notifications on my phone
                </label>

                <label style="display: flex; align-items: center; gap: 10px; font-size: 13px; margin-bottom: 14px; cursor: pointer;">
                    <input type="checkbox" name="email_notifications" value="1" @checked($settings->email_notifications)>
                    Email notifications
                </label>

                <div style="font-size: 12px; color: var(--or-slate); margin-bottom: 14px; line-height: 1.6;">
                    Account emails — approval, suspension, password resets — are always sent.
                </div>

                <x-ui.field label="Theme" name="theme" :value="$settings->theme"
                            :options="['system' => 'Match device', 'light' => 'Light', 'dark' => 'Dark']" />

                <button type="submit" class="or-btn or-btn-secondary" style="width: 100%;">Save preferences</button>
            </form>

            <form method="POST" action="{{ route('agent.profile.password') }}" class="or-card">
                @csrf
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Change password</h2>

                <x-ui.field label="Current password" name="current_password" type="password" required />
                <x-ui.field label="New password" name="new_password" type="password" required hint="At least 8 characters." />
                <x-ui.field label="Confirm new password" name="new_password_confirmation" type="password" required />

                <button type="submit" class="or-btn or-btn-secondary" style="width: 100%;">Update password</button>
            </form>
        </div>
    </div>
</x-layouts.app>
