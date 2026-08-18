<x-layouts.app title="My Profile — Agent Partner">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">My Profile & Service Coverage</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Update your brokerage details, real-estate license, and target ZIP codes for referral matching.</p>
    </div>

    <div class="or-card" style="max-width: 650px;">
        <form action="{{ route('agent.profile.update') }}" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
            @csrf

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">License Number</label>
                    <input type="text" name="license_number" value="{{ $profile->license_number ?? '' }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">License State</label>
                    <input type="text" name="license_state" value="{{ $profile->license_state ?? 'FL' }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Brokerage Name</label>
                <input type="text" name="brokerage" value="{{ $profile->brokerage ?? '' }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Service Cities (Comma separated)</label>
                <input type="text" name="service_cities" value="{{ is_array($profile->service_cities) ? implode(', ', $profile->service_cities) : '' }}" placeholder="Miami, Fort Lauderdale, Coral Gables" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Service ZIP Codes (Comma separated)</label>
                <input type="text" name="service_zips" value="{{ is_array($profile->service_zips) ? implode(', ', $profile->service_zips) : '' }}" placeholder="33139, 33141, 33134" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 10px; font-size: 14px; margin-top: 8px;">Save Profile & Coverage &rarr;</button>
        </form>
    </div>
</x-layouts.app>
