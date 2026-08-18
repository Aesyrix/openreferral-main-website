<x-layouts.app title="Agent Directory — Staff Portal">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Agent Capacity & Directory</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Check agent active lead capacity, ZIP coverage, and contact info before assigning leads.</p>
        </div>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>Agent Partner</th>
                    <th>Package</th>
                    <th>State & Brokerage</th>
                    <th>Coverage ZIPs</th>
                    <th>Complimentary Rem.</th>
                    <th>Active Leads</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agents as $agent)
                <tr>
                    <td>
                        <div style="font-weight: 700; color: #0B1B2B;">{{ $agent->name }}</div>
                        <div style="font-size: 12px; color: var(--or-slate);">{{ $agent->email }} · {{ $agent->phone }}</div>
                    </td>
                    <td><span class="or-badge badge-blue">{{ $agent->agentProfile->package->name ?? 'Basic' }}</span></td>
                    <td>{{ $agent->agentProfile->license_state ?? 'FL' }} — {{ $agent->agentProfile->brokerage ?? 'Independent' }}</td>
                    <td>{{ is_array($agent->agentProfile->service_zips) ? implode(', ', $agent->agentProfile->service_zips) : 'N/A' }}</td>
                    <td><strong>{{ $agent->agentProfile->complimentary_remaining ?? 0 }}</strong> remaining</td>
                    <td>{{ $agent->assigned_leads_count ?? 0 }} / {{ $agent->agentProfile->max_open_leads ?? 15 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $agents->links() }}
        </div>
    </div>
</x-layouts.app>
