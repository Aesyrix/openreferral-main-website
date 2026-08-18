<x-layouts.app title="Feedback · {{ $lead->reference_id }}">
    <x-ui.page-header
        :title="'Feedback for '.$lead->reference_id"
        :subtitle="$lead->name.' · currently '.$lead->status?->label()">
        <x-slot:actions>
            <a href="{{ route('admin.leads.show', $lead->id) }}" class="or-btn or-btn-secondary">← Back to lead</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="or-card">
        @if($lead->feedback->isEmpty())
            <x-ui.empty icon="💬" title="No feedback recorded"
                        message="The assigned agent has not accepted or rejected this lead yet." />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead><tr><th>Agent</th><th>Decision</th><th>Reason</th><th>Comments</th><th>Submitted</th></tr></thead>
                    <tbody>
                        @foreach($lead->feedback as $item)
                            <tr>
                                <td style="font-weight: 600; font-size: 13px;">{{ $item->agent?->name ?? '—' }}</td>
                                <td>
                                    <span class="or-badge {{ $item->accepted() ? 'badge-green' : 'badge-red' }}">
                                        {{ $item->accepted() ? 'Accepted' : 'Rejected' }}
                                    </span>
                                </td>
                                <td style="font-size: 13px;">{{ $item->reasonLabel() ?? '—' }}</td>
                                <td style="font-size: 13px; color: var(--or-slate); max-width: 420px; line-height: 1.6;">{{ $item->comments ?? '—' }}</td>
                                <td style="font-size: 12px; color: var(--or-slate); white-space: nowrap;">{{ $item->created_at?->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
