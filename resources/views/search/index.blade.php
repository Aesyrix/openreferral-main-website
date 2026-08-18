<x-layouts.app title="Search">
    <x-ui.page-header
        :title="'Search results for “'.$term.'”'"
        :subtitle="$leads->count().' lead(s)'.($agents->isNotEmpty() ? ' · '.$agents->count().' agent(s)' : '')" />

    <div class="or-card" style="padding: 0; overflow: hidden; margin-bottom: 18px;">
        <h2 style="font-size: 15px; font-weight: 700; margin: 0; padding: 18px 20px 12px;">Leads</h2>
        @if($leads->isEmpty())
            <x-ui.empty icon="🔍" title="No leads matched"
                        message="Try a reference like LEAD-10001, a client name, a phone number, or a location." />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead><tr><th>Reference</th><th>Client</th><th>Location</th><th>Status</th><th>Agent</th></tr></thead>
                    <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td>
                                    <a href="{{ auth()->user()->isStaff() ? route('admin.leads.show', $lead->id) : route('agent.leads.show', $lead->id) }}"
                                       style="color: var(--or-blue); text-decoration: none; font-weight: 700;">
                                        {{ $lead->reference_id }}
                                    </a>
                                </td>
                                <td style="font-size: 13px;">
                                    <div style="font-weight: 600;">{{ $lead->name }}</div>
                                    <div style="font-size: 12px; color: var(--or-slate);">{{ $lead->phone ?: $lead->email ?: '—' }}</div>
                                </td>
                                <td style="font-size: 13px; color: var(--or-slate);">{{ $lead->location }}</td>
                                <td><x-ui.lead-status :status="$lead->status" /></td>
                                <td style="font-size: 13px;">{{ $lead->assignedAgent?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if(auth()->user()->isStaff())
        <div class="or-card" style="padding: 0; overflow: hidden;">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0; padding: 18px 20px 12px;">Agents</h2>
            @if($agents->isEmpty())
                <x-ui.empty icon="🤝" title="No agents matched" />
            @else
                <div style="overflow-x: auto;">
                    <table class="or-table">
                        <thead><tr><th>Agent</th><th>Status</th><th>Brokerage</th><th>Contact</th></tr></thead>
                        <tbody>
                            @foreach($agents as $agent)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.agents.show', $agent->id) }}" style="color: var(--or-blue); text-decoration: none; font-weight: 600;">
                                            {{ $agent->name }}
                                        </a>
                                    </td>
                                    <td><x-ui.user-status :status="$agent->status" /></td>
                                    <td style="font-size: 13px;">{{ $agent->agentProfile?->brokerage ?? '—' }}</td>
                                    <td style="font-size: 13px; color: var(--or-slate);">{{ $agent->email }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
