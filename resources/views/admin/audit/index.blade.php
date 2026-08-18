<x-layouts.app title="Audit Log">
    <x-ui.page-header
        title="Audit Log"
        subtitle="Every privileged action — approvals, assignments, status changes, removals." />

    <div class="or-card" style="margin-bottom: 16px; padding: 14px 16px;">
        <form method="GET" action="{{ route('admin.audit.index') }}" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select name="action" style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                <option value="">All actions</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>

            <select name="subject_type" style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                <option value="">All subjects</option>
                @foreach(['lead', 'user', 'transaction', 'content_page', 'settings'] as $type)
                    <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 8px 18px;">Filter</button>
            @if(request()->hasAny(['action', 'subject_type', 'actor_id']))
                <a href="{{ route('admin.audit.index') }}" class="or-btn or-btn-secondary" style="padding: 8px 18px;">Clear</a>
            @endif
        </form>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        @if($logs->isEmpty())
            <x-ui.empty icon="📜" title="No matching audit entries" />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th><th>Details</th></tr></thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td style="font-size: 12px; color: var(--or-slate); white-space: nowrap;">
                                    {{ $log->created_at?->format('M j, Y g:i A') }}
                                </td>
                                <td style="font-size: 13px;">{{ $log->actor?->name ?? 'System' }}</td>
                                <td style="font-size: 13px;"><code>{{ $log->action }}</code></td>
                                <td style="font-size: 13px;">
                                    @if($log->subject_type === 'lead' && $log->subject_id)
                                        <a href="{{ route('admin.leads.show', $log->subject_id) }}" style="color: var(--or-blue); text-decoration: none;">lead #{{ $log->subject_id }}</a>
                                    @elseif($log->subject_type === 'user' && $log->subject_id)
                                        <a href="{{ route('admin.agents.show', $log->subject_id) }}" style="color: var(--or-blue); text-decoration: none;">user #{{ $log->subject_id }}</a>
                                    @elseif($log->subject_type)
                                        {{ $log->subject_type }} #{{ $log->subject_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="font-size: 12px; color: var(--or-slate); max-width: 380px;">
                                    @if($log->meta_json)
                                        <code style="font-size: 11px;">{{ Str::limit(json_encode($log->meta_json), 130) }}</code>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: 14px 16px;">{{ $logs->links() }}</div>
        @endif
    </div>
</x-layouts.app>
