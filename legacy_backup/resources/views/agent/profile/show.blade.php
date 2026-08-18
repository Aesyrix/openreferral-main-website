<x-layouts.app title="My Agent Profile">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">My Profile & Territory Coverage</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Update license details, brokerage information, and exclusive referral ZIP codes.</p>
    </div>

    <div class="or-card" style="max-width: 600px;">
        <form action="{{ route('agent.profile.update') }}" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
            @csrf

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $agent->name) }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Phone Number</label>
                <input type="text" name="phone" value="{{ old('phone', $agent->phone) }}" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Real Estate License #</label>
                <input type="text" name="license_number" value="{{ old('license_number', $profile->license_number ?? '') }}" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Brokerage Name</label>
                <input type="text" name="brokerage" value="{{ old('brokerage', $profile->brokerage ?? '') }}" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Service Cities (Comma-separated)</label>
                <input type="text" name="service_cities" value="{{ is_array($profile->service_cities ?? null) ? implode(', ', $profile->service_cities) : '' }}" placeholder="Miami, Miami Beach" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Exclusive Service ZIP Codes (Comma-separated)</label>
                <input type="text" name="service_zips" value="{{ is_array($profile->service_zips ?? null) ? implode(', ', $profile->service_zips) : '' }}" placeholder="33139, 33141" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 10px;">Save Profile Changes</button>
        </form>
    </div>
</x-layouts.app>
