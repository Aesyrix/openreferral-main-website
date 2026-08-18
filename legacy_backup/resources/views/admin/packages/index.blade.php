<x-layouts.app title="Package Catalog — Admin HQ">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Package Catalog & Pricing</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Manage referral packages, credit allotments, and referral fee percentages.</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
        @foreach($packages as $pkg)
        <div class="or-card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="font-size: 18px; font-weight: 800; color: #0B1B2B; margin: 0;">{{ $pkg->name }}</h3>
                    <span class="or-badge badge-blue">{{ strtoupper($pkg->billing) }}</span>
                </div>

                <div style="font-size: 24px; font-weight: 800; color: var(--or-blue); margin-bottom: 16px;">
                    ${{ number_format($pkg->price_cents / 100, 2) }}
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: var(--or-ink); margin-bottom: 16px;">
                    <div>• <strong>{{ $pkg->referral_fee_percent }}%</strong> Referral Fee</div>
                    <div>• <strong>{{ $pkg->yearly_referrals }}</strong> Yearly Referrals</div>
                    <div>• <strong>{{ $pkg->complimentary_referrals }}</strong> Complimentary Referrals</div>
                    <div>• <strong>{{ $pkg->max_cities }}</strong> Cities / <strong>{{ $pkg->max_zips }}</strong> ZIPs</div>
                    <div>• <strong>{{ $pkg->closing_guarantee_days }}</strong> Day Guarantee</div>
                </div>
            </div>

            <div style="border-top: 1px solid var(--or-line); padding-top: 12px; font-size: 11px; color: var(--or-slate);">
                Status: <strong style="color: var(--or-success);">Active</strong>
            </div>
        </div>
        @endforeach
    </div>
</x-layouts.app>
