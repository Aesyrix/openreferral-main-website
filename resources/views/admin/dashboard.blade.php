<x-layouts.app title="Overview">
    <x-ui.page-header
        title="Operations Overview"
        subtitle="Lead pipeline, agent approvals, and response performance across the network.">
        <x-slot:actions>
            <a href="{{ route('admin.leads.create') }}" class="or-btn or-btn-primary">+ New Lead</a>
            <a href="{{ route('admin.analytics.index') }}" class="or-btn or-btn-secondary">Full Analytics</a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- KPI row --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 22px;">
        <x-ui.stat label="Total Leads" :value="number_format($counts['total'])"
                   :href="route('admin.leads.index')" />
        <x-ui.stat label="Awaiting Response" :value="number_format($counts['new'])"
                   accent="var(--or-info)" hint="Assigned, not yet accepted"
                   :href="route('admin.leads.index', ['status' => 'new'])" />
        <x-ui.stat label="In Progress" :value="number_format($counts['in_progress'])"
                   accent="var(--or-warning)"
                   :href="route('admin.leads.index', ['status' => 'in_progress'])" />
        <x-ui.stat label="Converted" :value="number_format($counts['converted'])"
                   accent="var(--or-success)" :hint="$conversionRate.'% conversion rate'"
                   :href="route('admin.leads.index', ['status' => 'converted'])" />
        <x-ui.stat label="Rejected" :value="number_format($counts['rejected'])"
                   accent="var(--or-danger)"
                   :href="route('admin.leads.index', ['status' => 'rejected'])" />

        @if($totalRevenueCents !== null)
            <x-ui.stat label="Total Revenue" value="${{ number_format($totalRevenueCents / 100, 2) }}"
                       accent="var(--or-teal)"
                       hint="${{ number_format($monthRevenueCents / 100, 2) }} this month"
                       :href="route('admin.revenue.index')" />
        @endif
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 22px;">
        <x-ui.stat label="Active Agents" :value="number_format($activeAgents)" accent="var(--or-success)"
                   :href="route('admin.agents.index', ['status' => 'active'])" />
        <x-ui.stat label="Pending Approval" :value="number_format($pendingAgents->count())" accent="var(--or-warning)"
                   :href="route('admin.agents.index', ['status' => 'pending'])" />
        <x-ui.stat label="Suspended" :value="number_format($suspendedAgents)" accent="var(--or-orange)"
                   :href="route('admin.agents.index', ['status' => 'suspended'])" />
        <x-ui.stat label="Acceptance Rate" value="{{ $acceptanceRate }}%" accent="var(--or-purple)"
                   hint="Of leads agents responded to" />
        <x-ui.stat label="Avg Response Time"
                   :value="$avgResponseHours !== null ? $avgResponseHours.'h' : '—'"
                   accent="var(--or-blue-deep)" hint="Assignment → decision" />
    </div>

    {{-- Pending approvals: the thing that most needs a human --}}
    @if($pendingAgents->isNotEmpty())
        <div class="or-card" style="margin-bottom: 22px; border-left: 4px solid var(--or-warning);">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">
                🙋 {{ $pendingAgents->count() }} agent{{ $pendingAgents->count() === 1 ? '' : 's' }} awaiting approval
            </h2>
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead>
                        <tr><th>Agent</th><th>Email</th><th>Brokerage</th><th>Signed up</th><th style="text-align: right;">Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach($pendingAgents->take(5) as $agent)
                            <tr>
                                <td style="font-weight: 600;">
                                    <a href="{{ route('admin.agents.show', $agent->id) }}" style="color: var(--or-blue); text-decoration: none;">{{ $agent->name }}</a>
                                </td>
                                <td style="color: var(--or-slate);">{{ $agent->email }}</td>
                                <td style="color: var(--or-slate);">{{ $agent->agentProfile?->brokerage ?? '—' }}</td>
                                <td style="color: var(--or-slate);">{{ $agent->created_at?->diffForHumans() }}</td>
                                <td style="text-align: right;">
                                    <form method="POST" action="{{ route('admin.agents.approve', $agent->id) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="or-btn or-btn-success" style="padding: 5px 12px; font-size: 12px;">Approve</button>
                                    </form>
                                    <a href="{{ route('admin.agents.show', $agent->id) }}" class="or-btn or-btn-secondary" style="padding: 5px 12px; font-size: 12px;">Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($pendingAgents->count() > 5)
                <div style="margin-top: 10px;">
                    <a href="{{ route('admin.agents.index', ['status' => 'pending']) }}" style="font-size: 13px; color: var(--or-blue);">
                        See all {{ $pendingAgents->count() }} pending agents →
                    </a>
                </div>
            @endif
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px; margin-bottom: 22px;">
        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 4px 0;">Leads received — last 7 days</h2>
            <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 8px 0;">New leads created per day.</p>
            <x-ui.bar-chart :series="$trend" />
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Unassigned leads</h2>
            @if($unassigned->isEmpty())
                <x-ui.empty icon="✅" title="Everything is assigned" message="New leads will appear here until you route them to an agent." />
            @else
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    @foreach($unassigned as $lead)
                        <a href="{{ route('admin.leads.show', $lead->id) }}"
                           style="display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--or-line); border-radius: 8px; text-decoration: none; color: inherit;">
                            <div style="min-width: 0;">
                                <div style="font-weight: 600; font-size: 13px;">{{ $lead->reference_id }} · {{ $lead->name }}</div>
                                <div style="font-size: 12px; color: var(--or-slate); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $lead->intent?->label() }} · {{ $lead->location }}
                                </div>
                            </div>
                            <span class="or-badge badge-slate" style="flex-shrink: 0;">{{ $lead->created_at?->diffForHumans(null, true) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px;">
        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Waiting on an agent decision</h2>
            @if($awaitingResponse->isEmpty())
                <x-ui.empty icon="⏱" title="No leads waiting" message="Assigned leads appear here until the agent accepts or rejects." />
            @else
                <div style="overflow-x: auto;">
                    <table class="or-table">
                        <thead><tr><th>Lead</th><th>Agent</th><th>Waiting</th></tr></thead>
                        <tbody>
                            @foreach($awaitingResponse as $lead)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.leads.show', $lead->id) }}" style="color: var(--or-blue); text-decoration: none; font-weight: 600;">
                                            {{ $lead->reference_id }}
                                        </a>
                                        <div style="font-size: 12px; color: var(--or-slate);">{{ $lead->name }}</div>
                                    </td>
                                    <td style="font-size: 13px;">{{ $lead->assignedAgent?->name ?? '—' }}</td>
                                    <td>
                                        @php($hours = $lead->assigned_at ? $lead->assigned_at->diffInHours(now()) : null)
                                        <span class="{{ $hours === null ? 'sla-chip-green' : ($hours < 4 ? 'sla-chip-green' : ($hours < 24 ? 'sla-chip-amber' : 'sla-chip-red')) }}">
                                            {{ $lead->assigned_at ? $lead->assigned_at->diffForHumans(null, true) : '—' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Latest agent feedback</h2>
            @if($recentFeedback->isEmpty())
                <x-ui.empty icon="💬" title="No feedback yet" message="Accept/reject decisions from agents land here." />
            @else
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @foreach($recentFeedback as $item)
                        <div style="padding: 10px 12px; border-left: 3px solid {{ $item->accepted() ? 'var(--or-success)' : 'var(--or-danger)' }}; background-color: var(--or-paper); border-radius: 0 8px 8px 0;">
                            <div style="font-size: 13px;">
                                <strong>{{ $item->agent?->name ?? 'Agent' }}</strong>
                                {{ $item->accepted() ? 'accepted' : 'rejected' }}
                                <a href="{{ $item->lead ? route('admin.leads.show', $item->lead_id) : '#' }}" style="color: var(--or-blue); text-decoration: none;">
                                    {{ $item->lead?->reference_id ?? 'lead' }}
                                </a>
                            </div>
                            @if(! $item->accepted())
                                <div style="font-size: 12px; color: var(--or-danger); margin-top: 2px;">{{ $item->reasonLabel() }}</div>
                            @endif
                            @if($item->comments)
                                <div style="font-size: 12px; color: var(--or-slate); margin-top: 3px; font-style: italic;">“{{ Str::limit($item->comments, 110) }}”</div>
                            @endif
                            <div style="font-size: 11px; color: var(--or-slate); margin-top: 4px;">{{ $item->created_at?->diffForHumans() }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Recent activity</h2>
            @if($recentActivity->isEmpty())
                <x-ui.empty icon="📜" title="No activity logged yet" />
            @else
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    @foreach($recentActivity as $log)
                        <div style="display: flex; justify-content: space-between; gap: 10px; font-size: 13px; padding-bottom: 8px; border-bottom: 1px solid var(--or-line);">
                            <div>
                                <span style="font-weight: 600;">{{ $log->label() }}</span>
                                <div style="font-size: 12px; color: var(--or-slate);">
                                    {{ $log->actor?->name ?? 'System' }}
                                    @if($log->subject_type)· {{ $log->subject_type }} #{{ $log->subject_id }}@endif
                                </div>
                            </div>
                            <div style="font-size: 11px; color: var(--or-slate); white-space: nowrap;">{{ $log->created_at?->diffForHumans(null, true) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
