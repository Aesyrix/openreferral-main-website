<x-layouts.app title="Agent Profile — {{ $agent->name }}">
    <div style="margin-bottom: 20px;">
        <a href="{{ route('admin.agents.index') }}" class="or-btn or-btn-secondary" style="font-size: 12px; padding: 6px 12px; margin-bottom: 12px;">&larr; Back to Agent Roster</a>
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">{{ $agent->name }}</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">{{ $agent->agentProfile->brokerage ?? 'Independent Brokerage' }} · License: {{ $agent->agentProfile->license_number ?? 'N/A' }} ({{ $agent->agentProfile->license_state ?? 'FL' }})</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
        <div class="or-card">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Package & Coverage Specs</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Active Package</div>
                    <div style="font-size: 15px; font-weight: 700; color: var(--or-blue);">{{ $agent->agentProfile->package->name ?? 'None' }}</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Complimentary Credits Remaining</div>
                    <div style="font-size: 15px; font-weight: 700; color: #0B1B2B;">{{ $agent->agentProfile->complimentary_remaining ?? 0 }} Leads</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Referral Fee %</div>
                    <div style="font-size: 15px; font-weight: 700; color: #0B1B2B;">{{ $agent->agentProfile->referral_fee_percent ?? 20 }}%</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Service Cities</div>
                    <div style="font-size: 13px; font-weight: 600; color: #0B1B2B;">{{ is_array($agent->agentProfile->service_cities) ? implode(', ', $agent->agentProfile->service_cities) : 'None specified' }}</div>
                </div>
                <div>
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Service ZIPs</div>
                    <div style="font-size: 13px; font-weight: 600; color: #0B1B2B;">{{ is_array($agent->agentProfile->service_zips) ? implode(', ', $agent->agentProfile->service_zips) : 'None specified' }}</div>
                </div>
            </div>
        </div>

        <div class="or-card">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Assigned Referrals ({{ $assignedLeads->count() }})</h3>
            <table class="or-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Assigned Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignedLeads as $lead)
                    <tr>
                        <td><a href="{{ route('admin.leads.show', $lead->uuid) }}" style="color: var(--or-blue); font-weight: 700;">{{ $lead->public_ref }}</a></td>
                        <td>{{ $lead->full_name }}</td>
                        <td><span class="or-badge {{ is_object($lead->status) ? $lead->status->badgeClass() : 'badge-blue' }}">{{ is_object($lead->status) ? $lead->status->label() : ucfirst($lead->status) }}</span></td>
                        <td>{{ $lead->assigned_at ? $lead->assigned_at->format('M j, Y') : 'N/A' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="color: var(--or-slate);">No assigned leads currently.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
