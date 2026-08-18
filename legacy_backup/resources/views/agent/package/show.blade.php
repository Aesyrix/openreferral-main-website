<x-layouts.app title="Package & Closing Guarantee">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">My Package & Guarantee Clocks</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">View subscription renewal dates, remaining lead credits, and closing guarantee windows.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div class="or-card" style="border-top: 4px solid var(--or-blue);">
            <h3 style="font-size: 18px; font-weight: 800; color: #0B1B2B; margin: 0 0 8px 0;">
                {{ $profile->package->name ?? 'Standard Partner' }}
            </h3>
            <div style="font-size: 13px; color: var(--or-slate); margin-bottom: 16px;">
                Referral Fee Split: <strong>{{ $profile->referral_fee_percent ?? 20 }}%</strong>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--or-line); padding-bottom: 6px;">
                    <span>Subscription Status</span>
                    <strong style="color: var(--or-success);">{{ strtoupper($subscription->status->value ?? 'ACTIVE') }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--or-line); padding-bottom: 6px;">
                    <span>Renew / Expiry Date</span>
                    <strong>{{ $subscription->ends_at ? $subscription->ends_at->format('M j, Y') : 'N/A' }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--or-line); padding-bottom: 6px;">
                    <span>Days Remaining</span>
                    <strong>{{ $subscription->ends_at ? now()->diffInDays($subscription->ends_at) : 'N/A' }} Days</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--or-line); padding-bottom: 6px;">
                    <span>Complimentary Credits Left</span>
                    <strong style="color: var(--or-blue);">{{ $profile->complimentary_remaining ?? 0 }} Free Leads</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-bottom: 6px;">
                    <span>Closing Guarantee Window</span>
                    <strong>{{ $profile->closing_guarantee_days ?? 150 }} Days</strong>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
