<x-layouts.app title="Analytics">
    <x-ui.page-header
        title="Leads Analytics"
        subtitle="Pipeline, conversion, rejection reasons, and per-agent performance.">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.analytics.index') }}">
                <select name="range" onchange="this.form.submit()"
                        style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                    @foreach(['week' => 'Last 7 days', 'fortnight' => 'Last 14 days', 'month' => 'Last 30 days', 'quarter' => 'Last 90 days', 'year' => 'Last 365 days'] as $value => $label)
                        <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            @can('view-revenue')
                <a href="{{ route('admin.revenue.index') }}" class="or-btn or-btn-secondary">Revenue →</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
        <x-ui.stat label="Total leads" :value="number_format($counts['total'])" />
        <x-ui.stat label="Conversion rate" value="{{ $conversionRate }}%" accent="var(--or-success)"
                   :hint="number_format($counts['converted']).' converted'" />
        <x-ui.stat label="Acceptance rate" value="{{ $acceptanceRate }}%" accent="var(--or-purple)"
                   hint="Accepted ÷ responded" />
        <x-ui.stat label="Avg response time" :value="$avgResponseHours !== null ? $avgResponseHours.'h' : '—'"
                   accent="var(--or-blue-deep)" hint="Assignment → decision" />
        <x-ui.stat label="Rejected" :value="number_format($counts['rejected'])" accent="var(--or-danger)" />
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 18px; margin-bottom: 22px;">
        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 2px 0;">Leads created</h2>
            <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 8px;">New leads entering the pipeline.</p>
            <x-ui.bar-chart :series="$trend" />
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 2px 0;">Conversions</h2>
            <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 8px;">Leads marked converted per day.</p>
            <x-ui.bar-chart :series="$conversions" color="var(--or-success)" />
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 22px;">
        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Pipeline breakdown</h2>
            @foreach(\App\Enums\LeadStatus::cases() as $status)
                @php
                    $value = $counts[$status->value] ?? 0;
                    $percent = $counts['total'] > 0 ? round($value / $counts['total'] * 100, 1) : 0;
                    $colors = ['new' => 'var(--or-info)', 'in_progress' => 'var(--or-warning)', 'converted' => 'var(--or-success)', 'rejected' => 'var(--or-danger)'];
                @endphp
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                        <span>{{ $status->label() }}</span>
                        <span style="color: var(--or-slate);">{{ number_format($value) }} ({{ $percent }}%)</span>
                    </div>
                    <div style="height: 8px; background-color: var(--or-line); border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: {{ $percent }}%; background-color: {{ $colors[$status->value] }};"></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Why agents reject leads</h2>
            @if(empty($rejectionReasons))
                <x-ui.empty icon="👍" title="No rejections recorded"
                            message="Rejection reasons are captured every time an agent declines a lead." />
            @else
                @foreach($rejectionReasons as $reason)
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                            <span>{{ $reason['label'] }}</span>
                            <span style="color: var(--or-slate);">{{ $reason['count'] }} ({{ $reason['percent'] }}%)</span>
                        </div>
                        <div style="height: 8px; background-color: var(--or-line); border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: {{ $reason['percent'] }}%; background-color: var(--or-danger);"></div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <h2 style="font-size: 15px; font-weight: 700; margin: 0; padding: 18px 20px 12px;">Per-agent scorecard</h2>
        @if($scorecard->isEmpty())
            <x-ui.empty icon="🤝" title="No agents yet" />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead>
                        <tr>
                            <th>Agent</th><th>Status</th><th>Assigned</th><th>Pending</th>
                            <th>Accepted</th><th>Rejected</th><th>Converted</th>
                            <th>Conversion</th><th>Acceptance</th><th>Avg response</th>
                            @can('view-revenue')<th>Revenue</th>@endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scorecard as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.agents.show', $row->agent->id) }}" style="font-weight: 600; color: var(--or-blue); text-decoration: none;">
                                        {{ $row->agent->name }}
                                    </a>
                                    <div style="font-size: 12px; color: var(--or-slate);">{{ $row->agent->agentProfile?->brokerage ?? '—' }}</div>
                                </td>
                                <td><x-ui.user-status :status="$row->agent->status" /></td>
                                <td style="font-weight: 600;">{{ $row->assigned }}</td>
                                <td>{{ $row->pending }}</td>
                                <td style="color: var(--or-success);">{{ $row->accepted }}</td>
                                <td style="color: var(--or-danger);">{{ $row->rejected }}</td>
                                <td style="font-weight: 600;">{{ $row->converted }}</td>
                                <td>{{ $row->conversion_rate }}%</td>
                                <td>{{ $row->acceptance_rate }}%</td>
                                <td>{{ $row->avg_response_hours !== null ? $row->avg_response_hours.'h' : '—' }}</td>
                                @can('view-revenue')
                                    <td style="font-weight: 600;">${{ number_format($row->revenue_cents / 100, 2) }}</td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
