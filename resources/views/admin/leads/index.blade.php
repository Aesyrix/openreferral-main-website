<x-layouts.app title="Leads">
    <x-ui.page-header
        title="Leads"
        subtitle="Create leads, route them to agents, and follow every decision.">
        <x-slot:actions>
            <a href="{{ route('admin.leads.create') }}" class="or-btn or-btn-primary">+ New Lead</a>
        </x-slot:actions>
    </x-ui.page-header>

    @php($currentStatus = request('status'))
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
        <a href="{{ route('admin.leads.index', request()->except(['status', 'assignment', 'page'])) }}"
           class="or-badge {{ $currentStatus || request('assignment') ? 'badge-slate' : 'badge-blue' }}" style="text-decoration: none; padding: 7px 14px;">
            All ({{ $statusCounts->sum() }})
        </a>
        @foreach(\App\Enums\LeadStatus::cases() as $status)
            <a href="{{ route('admin.leads.index', array_merge(request()->except(['page', 'assignment']), ['status' => $status->value])) }}"
               class="or-badge {{ $currentStatus === $status->value ? $status->badgeClass() : 'badge-slate' }}"
               style="text-decoration: none; padding: 7px 14px;">
                {{ $status->label() }} ({{ $statusCounts[$status->value] ?? 0 }})
            </a>
        @endforeach
        <a href="{{ route('admin.leads.index', ['assignment' => 'unassigned']) }}"
           class="or-badge {{ request('assignment') === 'unassigned' ? 'badge-orange' : 'badge-slate' }}"
           style="text-decoration: none; padding: 7px 14px;">
            Unassigned ({{ $unassignedCount }})
        </a>
    </div>

    <div class="or-card" style="margin-bottom: 16px; padding: 14px 16px;">
        <form method="GET" action="{{ route('admin.leads.index') }}" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            @if($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Reference, name, phone, email or location…"
                   style="flex: 1; min-width: 220px; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">

            <select name="agent_id" style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                <option value="">All agents</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" @selected(request('agent_id') == $agent->id)>{{ $agent->name }}</option>
                @endforeach
            </select>

            <select name="intent" style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                <option value="">Any intent</option>
                <option value="buying" @selected(request('intent') === 'buying')>Buying</option>
                <option value="selling" @selected(request('intent') === 'selling')>Selling</option>
            </select>

            <select name="sort" style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
                <option value="price_high" @selected(request('sort') === 'price_high')>Budget: high → low</option>
                <option value="price_low" @selected(request('sort') === 'price_low')>Budget: low → high</option>
                <option value="name_az" @selected(request('sort') === 'name_az')>Name A–Z</option>
            </select>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 8px 18px;">Filter</button>
            @if(request()->hasAny(['q', 'status', 'agent_id', 'intent', 'sort', 'assignment']))
                <a href="{{ route('admin.leads.index') }}" class="or-btn or-btn-secondary" style="padding: 8px 18px;">Clear</a>
            @endif
        </form>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        @if($leads->isEmpty())
            <x-ui.empty icon="🎯" title="No leads match these filters">
                <div style="margin-top: 14px;">
                    <a href="{{ route('admin.leads.create') }}" class="or-btn or-btn-primary">Create the first lead</a>
                </div>
            </x-ui.empty>
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Client</th>
                            <th>Intent / Budget</th>
                            <th>Status</th>
                            <th>Agent</th>
                            <th>Latest decision</th>
                            <th>Created</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.leads.show', $lead->id) }}" style="color: var(--or-blue); text-decoration: none; font-weight: 700;">
                                        {{ $lead->reference_id }}
                                    </a>
                                    @if($lead->lead_fee_cents > 0)
                                        <div style="font-size: 11px; color: var(--or-slate);">${{ number_format($lead->lead_fee_dollars, 2) }} fee</div>
                                    @endif
                                </td>
                                <td style="font-size: 13px;">
                                    <div style="font-weight: 600;">{{ $lead->name }}</div>
                                    <div style="color: var(--or-slate); font-size: 12px;">{{ $lead->phone ?: $lead->email ?: '—' }}</div>
                                </td>
                                <td style="font-size: 13px;">
                                    <span class="or-badge {{ $lead->intent?->badgeClass() }}">{{ $lead->intent?->label() }}</span>
                                    <div style="color: var(--or-slate); font-size: 12px; margin-top: 3px;">
                                        {{ $lead->price_range ?? 'Budget not set' }}
                                    </div>
                                </td>
                                <td><x-ui.lead-status :status="$lead->status" /></td>
                                <td style="font-size: 13px;">
                                    @if($lead->assignedAgent)
                                        <a href="{{ route('admin.agents.show', $lead->assigned_to) }}" style="color: var(--or-ink); text-decoration: none;">
                                            {{ $lead->assignedAgent->name }}
                                        </a>
                                        <div style="font-size: 11px; color: var(--or-slate);">{{ $lead->assigned_at?->diffForHumans() }}</div>
                                    @else
                                        <span class="or-badge badge-orange">Unassigned</span>
                                    @endif
                                </td>
                                <td style="font-size: 12px;">
                                    @if($lead->latestFeedback)
                                        <span class="or-badge {{ $lead->latestFeedback->accepted() ? 'badge-green' : 'badge-red' }}">
                                            {{ $lead->latestFeedback->accepted() ? 'Accepted' : 'Rejected' }}
                                        </span>
                                        @if(! $lead->latestFeedback->accepted())
                                            <div style="color: var(--or-slate); margin-top: 3px;">{{ $lead->latestFeedback->reasonLabel() }}</div>
                                        @endif
                                    @else
                                        <span style="color: var(--or-slate);">—</span>
                                    @endif
                                </td>
                                <td style="font-size: 12px; color: var(--or-slate); white-space: nowrap;">{{ $lead->created_at?->format('M j, Y') }}</td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="{{ route('admin.leads.show', $lead->id) }}" class="or-btn or-btn-primary" style="padding: 5px 10px; font-size: 12px;">Open</a>
                                    <a href="{{ route('admin.leads.edit', $lead->id) }}" class="or-btn or-btn-secondary" style="padding: 5px 10px; font-size: 12px;">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="padding: 14px 16px;">{{ $leads->links() }}</div>
        @endif
    </div>
</x-layouts.app>
