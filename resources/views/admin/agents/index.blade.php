<x-layouts.app title="Agents">
    <x-ui.page-header
        title="Agent Roster"
        subtitle="Approve signups, review performance, and manage access." />

    {{-- Status filter tabs --}}
    @php($current = request('status'))
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
        <a href="{{ route('admin.agents.index', request()->except(['status', 'page'])) }}"
           class="or-badge {{ $current ? 'badge-slate' : 'badge-blue' }}" style="text-decoration: none; padding: 7px 14px;">
            All ({{ $statusCounts->sum() }})
        </a>
        @foreach(\App\Enums\UserStatus::cases() as $status)
            <a href="{{ route('admin.agents.index', array_merge(request()->except('page'), ['status' => $status->value])) }}"
               class="or-badge {{ $current === $status->value ? $status->badgeClass() : 'badge-slate' }}"
               style="text-decoration: none; padding: 7px 14px;">
                {{ $status->label() }} ({{ $statusCounts[$status->value] ?? 0 }})
            </a>
        @endforeach
    </div>

    <div class="or-card" style="margin-bottom: 16px; padding: 14px 16px;">
        <form method="GET" action="{{ route('admin.agents.index') }}" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            @if($current)<input type="hidden" name="status" value="{{ $current }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email or phone…"
                   style="flex: 1; min-width: 220px; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
            <button type="submit" class="or-btn or-btn-primary" style="padding: 8px 18px;">Search</button>
            @if(request()->hasAny(['q', 'status']))
                <a href="{{ route('admin.agents.index') }}" class="or-btn or-btn-secondary" style="padding: 8px 18px;">Clear</a>
            @endif
        </form>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        @if($agents->isEmpty())
            <x-ui.empty icon="🤝" title="No agents match this view"
                        message="Agents appear here as soon as they sign up in the mobile app." />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Status</th>
                            <th>Brokerage / License</th>
                            <th>Service areas</th>
                            <th>Leads</th>
                            <th>Joined</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($agents as $agent)
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="{{ $agent->avatar_url }}" alt="" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover;">
                                        <div>
                                            <a href="{{ route('admin.agents.show', $agent->id) }}" style="font-weight: 600; color: var(--or-blue); text-decoration: none;">
                                                {{ $agent->name }}
                                            </a>
                                            @if($agent->agentProfile?->verified)
                                                <span title="Verified" style="color: var(--or-blue);">✔</span>
                                            @endif
                                            <div style="font-size: 12px; color: var(--or-slate);">{{ $agent->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><x-ui.user-status :status="$agent->status" /></td>
                                <td style="font-size: 13px;">
                                    {{ $agent->agentProfile?->brokerage ?? '—' }}
                                    <div style="font-size: 12px; color: var(--or-slate);">
                                        {{ $agent->agentProfile?->license_number ? $agent->agentProfile->license_number.' · '.$agent->agentProfile->license_state : 'No license on file' }}
                                    </div>
                                </td>
                                <td style="font-size: 12px; color: var(--or-slate); max-width: 200px;">
                                    {{ $agent->serviceAreas->pluck('area')->join(', ') ?: '—' }}
                                </td>
                                <td style="font-size: 13px;">
                                    <strong>{{ $agent->assigned_leads_count }}</strong> assigned
                                    <div style="font-size: 12px; color: var(--or-success);">{{ $agent->converted_leads_count }} converted</div>
                                </td>
                                <td style="font-size: 12px; color: var(--or-slate);">{{ $agent->created_at?->format('M j, Y') }}</td>
                                <td style="text-align: right; white-space: nowrap;">
                                    @if($agent->status === \App\Enums\UserStatus::PENDING)
                                        <form method="POST" action="{{ route('admin.agents.approve', $agent->id) }}" style="display: inline;">
                                            @csrf
                                            <button class="or-btn or-btn-success" style="padding: 5px 10px; font-size: 12px;">Approve</button>
                                        </form>
                                    @elseif($agent->status === \App\Enums\UserStatus::ACTIVE)
                                        <form method="POST" action="{{ route('admin.agents.suspend', $agent->id) }}" style="display: inline;"
                                              onsubmit="return confirm('Suspend {{ $agent->name }}? Their open leads return to the pool.')">
                                            @csrf
                                            <button class="or-btn or-btn-secondary" style="padding: 5px 10px; font-size: 12px;">Suspend</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.agents.restore', $agent->id) }}" style="display: inline;">
                                            @csrf
                                            <button class="or-btn or-btn-secondary" style="padding: 5px 10px; font-size: 12px;">Reactivate</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.agents.show', $agent->id) }}" class="or-btn or-btn-primary" style="padding: 5px 10px; font-size: 12px;">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="padding: 14px 16px;">{{ $agents->links() }}</div>
        @endif
    </div>
</x-layouts.app>
