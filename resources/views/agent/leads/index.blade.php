<x-layouts.app title="My Leads">
    <x-ui.page-header
        title="My Leads"
        subtitle="Every lead assigned to you, with the decision you made on each." />

    @php($currentStatus = request('status'))
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
        <a href="{{ route('agent.leads.index', request()->except(['status', 'page'])) }}"
           class="or-badge {{ $currentStatus ? 'badge-slate' : 'badge-blue' }}" style="text-decoration: none; padding: 7px 14px;">
            All ({{ $statusCounts->sum() }})
        </a>
        @foreach(\App\Enums\LeadStatus::cases() as $status)
            <a href="{{ route('agent.leads.index', array_merge(request()->except('page'), ['status' => $status->value])) }}"
               class="or-badge {{ $currentStatus === $status->value ? $status->badgeClass() : 'badge-slate' }}"
               style="text-decoration: none; padding: 7px 14px;">
                {{ $status->label() }} ({{ $statusCounts[$status->value] ?? 0 }})
            </a>
        @endforeach
    </div>

    <div class="or-card" style="margin-bottom: 16px; padding: 14px 16px;">
        <form method="GET" action="{{ route('agent.leads.index') }}" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            @if($currentStatus)<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search reference, name or location…"
                   style="flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
            <select name="sort" style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
                <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
                <option value="price_high" @selected(request('sort') === 'price_high')>Budget: high → low</option>
                <option value="price_low" @selected(request('sort') === 'price_low')>Budget: low → high</option>
                <option value="name_az" @selected(request('sort') === 'name_az')>Name A–Z</option>
            </select>
            <button type="submit" class="or-btn or-btn-primary" style="padding: 8px 18px;">Filter</button>
        </form>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        @if($leads->isEmpty())
            <x-ui.empty icon="🎯" title="No leads here yet"
                        message="When staff assign you a lead you'll get a push notification and it'll appear in this list." />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead>
                        <tr><th>Reference</th><th>Client</th><th>Intent / Budget</th><th>Location</th><th>Status</th><th>Assigned</th><th style="text-align: right;"></th></tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td>
                                    <a href="{{ route('agent.leads.show', $lead->id) }}" style="color: var(--or-blue); text-decoration: none; font-weight: 700;">
                                        {{ $lead->reference_id }}
                                    </a>
                                </td>
                                <td style="font-size: 13px;">
                                    <div style="font-weight: 600;">{{ $lead->name }}</div>
                                    <div style="font-size: 12px; color: var(--or-slate);">{{ $lead->phone ?: $lead->email ?: '—' }}</div>
                                </td>
                                <td style="font-size: 13px;">
                                    <span class="or-badge {{ $lead->intent?->badgeClass() }}">{{ $lead->intent?->label() }}</span>
                                    <div style="font-size: 12px; color: var(--or-slate); margin-top: 3px;">{{ $lead->price_range ?? '—' }}</div>
                                </td>
                                <td style="font-size: 13px; color: var(--or-slate); max-width: 220px;">{{ $lead->location }}</td>
                                <td><x-ui.lead-status :status="$lead->status" /></td>
                                <td style="font-size: 12px; color: var(--or-slate); white-space: nowrap;">{{ $lead->assigned_at?->diffForHumans() ?? '—' }}</td>
                                <td style="text-align: right;">
                                    <a href="{{ route('agent.leads.show', $lead->id) }}" class="or-btn {{ $lead->isRespondable() ? 'or-btn-primary' : 'or-btn-secondary' }}" style="padding: 5px 12px; font-size: 12px;">
                                        {{ $lead->isRespondable() ? 'Respond' : 'View' }}
                                    </a>
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
