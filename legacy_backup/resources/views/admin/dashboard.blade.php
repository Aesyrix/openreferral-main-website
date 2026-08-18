<x-layouts.app title="Admin Dashboard">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">HQ Executive Operations Overview</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Real-time performance metrics, referral revenue, and SLA SLA indicators.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('admin.leads.create') }}" class="or-btn or-btn-primary">+ Create Lead</a>
            <a href="{{ route('leads.export') }}?format=xlsx" class="or-btn or-btn-secondary">📥 Export XLSX</a>
        </div>
    </div>

    <!-- KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="or-card or-kpi-card">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600; text-transform: uppercase;">Total Network Leads</div>
            <div style="font-size: 28px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ number_format($totalLeads) }}</div>
            <div style="font-size: 11px; color: var(--or-success); font-weight: 600; margin-top: 4px;">+{{ $newToday }} new leads today</div>
        </div>

        <div class="or-card or-kpi-card" style="border-left-color: #E0A106;">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600; text-transform: uppercase;">Unassigned Pool</div>
            <div style="font-size: 28px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $unassigned }}</div>
            <div style="font-size: 11px; color: var(--or-slate); margin-top: 4px;">Awaiting staff assignment</div>
        </div>

        <div class="or-card or-kpi-card" style="border-left-color: #7C3AED;">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600; text-transform: uppercase;">Pending Acceptance</div>
            <div style="font-size: 28px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $pendingAccept }}</div>
            <div style="font-size: 11px; color: var(--or-info); margin-top: 4px;">Active SLA timers</div>
        </div>

        <div class="or-card or-kpi-card" style="border-left-color: var(--or-danger);">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600; text-transform: uppercase;">SLA Breached</div>
            <div style="font-size: 28px; font-weight: 800; color: var(--or-danger); margin-top: 4px;">{{ $slaBreached }}</div>
            <div style="font-size: 11px; color: var(--or-danger); margin-top: 4px;">Needs immediate re-assignment</div>
        </div>

        <div class="or-card or-kpi-card" style="border-left-color: var(--or-success);">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600; text-transform: uppercase;">Closed Revenue MTD</div>
            <div style="font-size: 28px; font-weight: 800; color: var(--or-success); margin-top: 4px;">${{ number_format($revenueMTD, 2) }}</div>
            <div style="font-size: 11px; color: var(--or-slate); margin-top: 4px;">{{ $closedMTDCount }} closed deals this month</div>
        </div>
    </div>

    <!-- Charts & Tables Layout -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">
        <!-- Top Performing Agents Table -->
        <div class="or-card">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Top Agent Partner Performance</h3>
            <table class="or-table">
                <thead>
                    <tr>
                        <th>Agent Partner</th>
                        <th>Assigned</th>
                        <th>Accept Rate</th>
                        <th>Closed Deals</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topAgents as $item)
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: #0B1B2B;">{{ $item['agent']->name }}</div>
                            <div style="font-size: 11px; color: var(--or-slate);">{{ $item['agent']->agentProfile ? $item['agent']->agentProfile->brokerage : 'Brokerage N/A' }}</div>
                        </td>
                        <td>{{ $item['total_assigned'] }}</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="flex: 1; height: 6px; background-color: #E2E8F0; border-radius: 3px; overflow: hidden; width: 60px;">
                                    <div style="height: 100%; width: {{ $item['accept_rate'] }}%; background-color: var(--or-blue);"></div>
                                </div>
                                <span style="font-weight: 600; font-size: 12px;">{{ $item['accept_rate'] }}%</span>
                            </div>
                        </td>
                        <td><strong>{{ $item['closed_count'] }}</strong></td>
                        <td>
                            <a href="{{ route('admin.agents.show', $item['agent']->uuid) }}" class="or-btn or-btn-secondary" style="padding: 4px 8px; font-size: 12px;">View Profile</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- System Activity Feed -->
        <div class="or-card">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Live Operations Feed</h3>
            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 380px; overflow-y: auto;">
                @foreach($recentActivities as $act)
                <div style="border-bottom: 1px solid var(--or-line); padding-bottom: 8px;">
                    <div style="font-size: 12px; font-weight: 600; color: #0B1B2B;">{{ $act->body }}</div>
                    <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--or-slate); margin-top: 4px;">
                        <span>By {{ $act->user ? $act->user->name : 'System' }}</span>
                        <span>{{ $act->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
