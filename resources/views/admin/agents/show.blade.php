<x-layouts.app :title="$agent->name">
    <x-ui.page-header
        :title="$agent->name"
        :subtitle="$agent->agentProfile?->title.' · '.($agent->agentProfile?->brokerage ?? 'No brokerage on file')">
        <x-slot:actions>
            <a href="{{ route('admin.agents.index') }}" class="or-btn or-btn-secondary">← Roster</a>
            <a href="{{ route('admin.leads.index', ['agent_id' => $agent->id]) }}" class="or-btn or-btn-secondary">Their leads</a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Approval actions --}}
    <div class="or-card" style="margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <img src="{{ $agent->avatar_url }}" alt="" style="width: 54px; height: 54px; border-radius: 50%; object-fit: cover;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <x-ui.user-status :status="$agent->status" />
                    @if($agent->agentProfile?->verified)
                        <span class="or-badge badge-blue">✔ Verified</span>
                    @endif
                </div>
                <div style="font-size: 13px; color: var(--or-slate); margin-top: 5px;">
                    {{ $agent->email }} @if($agent->phone) · {{ $agent->phone }} @endif
                </div>
                <div style="font-size: 12px; color: var(--or-slate); margin-top: 2px;">
                    Joined {{ $agent->created_at?->format('M j, Y') }}
                    @if($agent->last_login_at) · last seen {{ $agent->last_login_at->diffForHumans() }} @endif
                    @if($agent->agentProfile?->approved_at)
                        · approved by {{ $agent->agentProfile->approver?->name ?? 'staff' }} {{ $agent->agentProfile->approved_at->diffForHumans() }}
                    @endif
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start;">
            @if($agent->status === \App\Enums\UserStatus::PENDING)
                <form method="POST" action="{{ route('admin.agents.approve', $agent->id) }}">
                    @csrf
                    <button class="or-btn or-btn-success">✓ Approve agent</button>
                </form>
                <form method="POST" action="{{ route('admin.agents.reject', $agent->id) }}"
                      style="display: flex; gap: 6px;" onsubmit="return confirm('Reject this application?')">
                    @csrf
                    <input type="text" name="reason" placeholder="Reason (optional)" maxlength="255"
                           style="padding: 8px 10px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px; width: 190px;">
                    <button class="or-btn or-btn-danger">Reject</button>
                </form>
            @elseif($agent->status === \App\Enums\UserStatus::ACTIVE)
                <form method="POST" action="{{ route('admin.agents.verified', $agent->id) }}">
                    @csrf
                    <button class="or-btn or-btn-secondary">
                        {{ $agent->agentProfile?->verified ? 'Remove verified badge' : 'Mark verified' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.agents.suspend', $agent->id) }}" style="display: flex; gap: 6px;"
                      onsubmit="return confirm('Suspend this agent? Their open leads return to the pool.')">
                    @csrf
                    <input type="text" name="reason" placeholder="Reason (optional)" maxlength="255"
                           style="padding: 8px 10px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px; width: 170px;">
                    <button class="or-btn or-btn-secondary">Suspend</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.agents.restore', $agent->id) }}">
                    @csrf
                    <button class="or-btn or-btn-success">Reactivate</button>
                </form>
            @endif

            <button type="button" class="or-btn or-btn-danger" onclick="document.getElementById('danger-zone').scrollIntoView({behavior:'smooth'})">
                Delete permanently
            </button>
        </div>
    </div>

    @if($agent->agentProfile?->rejected_reason)
        <div style="background-color: #FEF3C7; border: 1px solid #FCD34D; color: #92400E; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <strong>Rejection reason on file:</strong> {{ $agent->agentProfile->rejected_reason }}
        </div>
    @endif

    {{-- Performance --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 20px;">
        <x-ui.stat label="Assigned" :value="number_format($counts['total'])" />
        <x-ui.stat label="Awaiting response" :value="number_format($counts['new'])" accent="var(--or-info)" />
        <x-ui.stat label="In progress" :value="number_format($counts['in_progress'])" accent="var(--or-warning)" />
        <x-ui.stat label="Converted" :value="number_format($counts['converted'])" accent="var(--or-success)" :hint="$conversionRate.'% of assigned'" />
        <x-ui.stat label="Rejected" :value="number_format($counts['rejected'])" accent="var(--or-danger)" :hint="$acceptanceRate.'% acceptance'" />
        <x-ui.stat label="Avg response" :value="$avgResponseHours !== null ? $avgResponseHours.'h' : '—'" accent="var(--or-purple)" />
        @can('view-revenue')
            <x-ui.stat label="Revenue billed" value="${{ number_format($revenueCents / 100, 2) }}" accent="var(--or-teal)" />
        @endcan
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 20px;">
        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Profile</h2>
            <table style="width: 100%; font-size: 13px;">
                <tr><td style="color: var(--or-slate); padding: 6px 0; width: 130px;">Title</td><td>{{ $agent->agentProfile?->title ?? '—' }}</td></tr>
                <tr><td style="color: var(--or-slate); padding: 6px 0;">Brokerage</td><td>{{ $agent->agentProfile?->brokerage ?? '—' }}</td></tr>
                <tr><td style="color: var(--or-slate); padding: 6px 0;">License</td><td>{{ $agent->agentProfile?->license_number ?? '—' }} {{ $agent->agentProfile?->license_state }}</td></tr>
                <tr><td style="color: var(--or-slate); padding: 6px 0;">Service areas</td><td>{{ $agent->serviceAreas->pluck('area')->join(', ') ?: '—' }}</td></tr>
                <tr><td style="color: var(--or-slate); padding: 6px 0;">Push devices</td><td>{{ $agent->devices->count() }} registered</td></tr>
                <tr>
                    <td style="color: var(--or-slate); padding: 6px 0;">Notifications</td>
                    <td>
                        Push {{ $agent->settingsOrDefault()->push_notifications ? 'on' : 'off' }} ·
                        Email {{ $agent->settingsOrDefault()->email_notifications ? 'on' : 'off' }}
                    </td>
                </tr>
            </table>
            @if($agent->agentProfile?->bio)
                <p style="font-size: 13px; color: var(--or-slate); line-height: 1.6; margin: 12px 0 0; padding-top: 12px; border-top: 1px solid var(--or-line);">
                    {{ $agent->agentProfile->bio }}
                </p>
            @endif
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Why they reject leads</h2>
            @if(empty($rejectionReasons))
                <x-ui.empty icon="👍" title="No rejections on record" />
            @else
                @foreach($rejectionReasons as $reason)
                    <div style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 3px;">
                            <span>{{ $reason['label'] }}</span>
                            <span style="color: var(--or-slate);">{{ $reason['count'] }} ({{ $reason['percent'] }}%)</span>
                        </div>
                        <div style="height: 7px; background-color: var(--or-line); border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: {{ $reason['percent'] }}%; background-color: var(--or-danger);"></div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="or-card" style="margin-bottom: 20px;">
        <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Feedback history</h2>
        @if($feedback->isEmpty())
            <x-ui.empty icon="💬" title="No feedback submitted yet" />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead><tr><th>Lead</th><th>Decision</th><th>Reason</th><th>Comments</th><th>When</th></tr></thead>
                    <tbody>
                        @foreach($feedback as $item)
                            <tr>
                                <td>
                                    <a href="{{ $item->lead ? route('admin.leads.show', $item->lead_id) : '#' }}" style="color: var(--or-blue); text-decoration: none; font-weight: 600;">
                                        {{ $item->lead?->reference_id ?? '—' }}
                                    </a>
                                </td>
                                <td>
                                    <span class="or-badge {{ $item->accepted() ? 'badge-green' : 'badge-red' }}">
                                        {{ $item->accepted() ? 'Accepted' : 'Rejected' }}
                                    </span>
                                </td>
                                <td style="font-size: 13px;">{{ $item->reasonLabel() ?? '—' }}</td>
                                <td style="font-size: 13px; color: var(--or-slate); max-width: 320px;">{{ $item->comments ?? '—' }}</td>
                                <td style="font-size: 12px; color: var(--or-slate); white-space: nowrap;">{{ $item->created_at?->format('M j, g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <h2 style="font-size: 15px; font-weight: 700; margin: 0; padding: 18px 20px 12px;">Assigned leads</h2>
        @if($leads->isEmpty())
            <x-ui.empty icon="🎯" title="No leads assigned yet" />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead><tr><th>Reference</th><th>Client</th><th>Status</th><th>Assigned</th><th>Responded</th></tr></thead>
                    <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td><a href="{{ route('admin.leads.show', $lead->id) }}" style="color: var(--or-blue); text-decoration: none; font-weight: 600;">{{ $lead->reference_id }}</a></td>
                                <td style="font-size: 13px;">{{ $lead->name }}<div style="font-size: 12px; color: var(--or-slate);">{{ $lead->location }}</div></td>
                                <td><x-ui.lead-status :status="$lead->status" /></td>
                                <td style="font-size: 12px; color: var(--or-slate);">{{ $lead->assigned_at?->format('M j, g:i A') ?? '—' }}</td>
                                <td style="font-size: 12px; color: var(--or-slate);">
                                    {{ $lead->responded_at?->format('M j, g:i A') ?? '—' }}
                                    @if($lead->responseHours() !== null)
                                        <div>({{ $lead->responseHours() }}h)</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: 14px 16px;">{{ $leads->links() }}</div>
        @endif
    </div>

    {{-- ===================================================== Danger zone --}}
    <div id="danger-zone" class="or-card" style="margin-top: 22px; border: 1px solid #FCA5A5; border-left: 4px solid var(--or-danger); background-color: #FFFBFB;">
        <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 4px; color: #991B1B;">Delete permanently</h2>
        <p style="font-size: 13px; color: var(--or-slate); margin: 0 0 14px; line-height: 1.7;">
            Removes {{ $agent->name }} from the database for good. This cannot be undone — there is no
            restore. If you only want to stop them receiving leads, <strong>suspend</strong> them instead.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
            <div style="padding: 12px 14px; background-color: #FEE2E2; border-radius: 8px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #991B1B; margin-bottom: 6px;">
                    Deleted with them
                </div>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.8; color: #7F1D1D;">
                    <li>Login, password and API tokens</li>
                    <li>Profile, licence and service areas</li>
                    <li>{{ $impact['devices'] }} registered device(s)</li>
                    <li>{{ number_format($impact['notifications']) }} notification(s) in their inbox</li>
                </ul>
            </div>

            <div style="padding: 12px 14px; background-color: #DCFCE7; border-radius: 8px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #15803D; margin-bottom: 6px;">
                    Kept (person detached)
                </div>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.8; color: #14532D;">
                    <li>{{ number_format($impact['transactions']) }} transaction(s) — ${{ number_format($impact['revenue_cents'] / 100, 2) }} revenue</li>
                    <li>{{ number_format($impact['feedback']) }} feedback record(s)</li>
                    <li>{{ number_format($impact['leads_total']) }} lead(s) — history intact</li>
                    <li>Audit log entries</li>
                </ul>
            </div>
        </div>

        @if($impact['leads_unassigned'] > 0)
            <div style="padding: 12px 14px; background-color: #FEF3C7; border-radius: 8px; font-size: 13px; color: #92400E; margin-bottom: 16px; line-height: 1.6;">
                <strong>{{ $impact['leads_unassigned'] }} open lead(s)</strong> will go back to the unassigned pool
                for you to route to someone else.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.agents.destroy', $agent->id) }}"
              onsubmit="return this.confirm_name.value.trim() === @js($agent->name)
                        || (alert('Type the agent name exactly to confirm.') , false)">
            @csrf @method('DELETE')

            <label for="confirm_name" style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                Type <code style="background-color: #FEE2E2; padding: 2px 6px; border-radius: 4px;">{{ $agent->name }}</code> to confirm
            </label>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <input id="confirm_name" name="confirm_name" type="text" autocomplete="off" required
                       placeholder="{{ $agent->name }}"
                       style="flex: 1; min-width: 240px; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px;">
                <button type="submit" class="or-btn or-btn-danger" style="padding: 10px 22px;">
                    Delete {{ $agent->name }} permanently
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
