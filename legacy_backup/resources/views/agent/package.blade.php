<x-layouts.app title="Package & Guarantee — Agent Partner">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">My Active Package & Closing Guarantee</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">View your current subscription tier, lead credit allotment, and guarantee terms.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="or-card">
            <h3 style="font-size: 18px; font-weight: 800; color: #0B1B2B; margin: 0 0 16px 0;">Active Subscription</h3>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Tier Name</div>
                    <div style="font-size: 20px; font-weight: 800; color: var(--or-blue);">{{ $package->name ?? 'Prime Lead' }}</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Referral Fee Rate</div>
                    <div style="font-size: 16px; font-weight: 700; color: #0B1B2B;">{{ $package->referral_fee_percent ?? 20 }}%</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Complimentary Lead Credits Remaining</div>
                    <div style="font-size: 22px; font-weight: 800; color: var(--or-success);">{{ $agentProfile->complimentary_remaining ?? 2 }} Leads</div>
                </div>
            </div>
        </div>

        <div class="or-card" style="background-color: var(--or-navy); color: white;">
            <h3 style="font-size: 18px; font-weight: 800; color: white; margin: 0 0 12px 0;">🛡️ {{ $package->closing_guarantee_days ?? 150 }}-Day Closing Guarantee</h3>
            <p style="font-size: 13px; color: #CBD5E1; line-height: 1.5; margin-bottom: 16px;">
                Our referral network guarantees your lead delivery. If a lead does not close within {{ $package->closing_guarantee_days ?? 150 }} days despite proper agent follow-up, our operations team provides a replacement qualified referral.
            </p>
            <div style="font-size: 12px; color: #94A3B8;">
                Subscription Expiration: <strong>{{ $subscription->ends_at ? $subscription->ends_at->format('M j, Y') : 'Active' }}</strong>
            </div>
        </div>
    </div>
</x-layouts.app>
