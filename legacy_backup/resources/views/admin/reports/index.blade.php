<x-layouts.app title="Revenue & Reports — Admin HQ">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Revenue & Performance Reports</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Closed referral fee breakdown, agent acceptance rates, and pipeline volume.</p>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <div class="or-card">
            <div style="font-size: 12px; color: var(--or-slate); text-transform: uppercase;">Total Closed Sales Volume</div>
            <div style="font-size: 22px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">
                ${{ number_format(($closedVolumeCents ?? 0)/100, 2) }}
            </div>
        </div>
        <div class="or-card">
            <div style="font-size: 12px; color: var(--or-slate); text-transform: uppercase;">Total Referral Fee Revenue</div>
            <div style="font-size: 22px; font-weight: 800; color: var(--or-success); margin-top: 4px;">
                ${{ number_format(($referralFeeRevenueCents ?? 0)/100, 2) }}
            </div>
        </div>
        <div class="or-card">
            <div style="font-size: 12px; color: var(--or-slate); text-transform: uppercase;">Average Fee Rate</div>
            <div style="font-size: 22px; font-weight: 800; color: var(--or-blue); margin-top: 4px;">
                {{ number_format($avgFeePct ?? 20.0, 1) }}%
            </div>
        </div>
        <div class="or-card">
            <div style="font-size: 12px; color: var(--or-slate); text-transform: uppercase;">Total Closed Deals</div>
            <div style="font-size: 22px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">
                {{ $closedDealsCount ?? 0 }}
            </div>
        </div>
    </div>

    <!-- Agent Leaderboard -->
    <div class="or-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--or-line); font-weight: 700; color: #0B1B2B;">
            Agent Revenue & Performance Breakdown
        </div>
        <table class="or-table">
            <thead>
                <tr>
                    <th>Agent Partner</th>
                    <th>Package</th>
                    <th>Closed Deals</th>
                    <th>Volume Closed</th>
                    <th>Referral Fees Earned</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agentStats as $st)
                <tr>
                    <td><strong>{{ $st->name }}</strong></td>
                    <td><span class="or-badge badge-blue">{{ $st->agentProfile->package->name ?? 'Basic' }}</span></td>
                    <td>{{ $st->closed_count ?? 0 }}</td>
                    <td>${{ number_format(($st->closed_volume_cents ?? 0)/100, 2) }}</td>
                    <td><strong style="color: var(--or-success);">${{ number_format(($st->referral_fee_earned_cents ?? 0)/100, 2) }}</strong></td>
                </tr>
                @empty
                <tr><td colspan="5" style="color: var(--or-slate);">No closed revenue recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
