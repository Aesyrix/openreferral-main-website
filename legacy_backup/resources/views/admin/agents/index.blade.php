<x-layouts.app title="Agent Roster — Admin HQ">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Agent Partner Roster</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Licensed partner network, package tiers, and coverage ZIPs.</p>
        </div>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>Agent Name</th>
                    <th>Package Tier</th>
                    <th>License / State</th>
                    <th>Brokerage</th>
                    <th>Complimentary Rem.</th>
                    <th>Open Leads</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agents as $agent)
                <tr>
                    <td>
                        <div style="font-weight: 700; color: #0B1B2B;">{{ $agent->name }}</div>
                        <div style="font-size: 12px; color: var(--or-slate);">{{ $agent->email }}</div>
                    </td>
                    <td>
                        <span class="or-badge badge-blue">
                            {{ $agent->agentProfile->package->name ?? 'Basic' }}
                        </span>
                    </td>
                    <td>{{ $agent->agentProfile->license_number ?? 'N/A' }} ({{ $agent->agentProfile->license_state ?? 'FL' }})</td>
                    <td>{{ $agent->agentProfile->brokerage ?? 'Independent' }}</td>
                    <td><strong>{{ $agent->agentProfile->complimentary_remaining ?? 0 }}</strong> remaining</td>
                    <td>{{ $agent->assigned_leads_count ?? 0 }} active</td>
                    <td>
                        <a href="{{ route('admin.agents.show', $agent->id) }}" class="or-btn or-btn-secondary" style="font-size: 11px; padding: 4px 8px;">View Profile</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $agents->links() }}
        </div>
    </div>
</x-layouts.app>
