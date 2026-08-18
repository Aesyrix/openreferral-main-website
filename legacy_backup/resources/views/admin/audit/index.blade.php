<x-layouts.app title="Audit Logs — Admin HQ">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">System Audit Logs</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Immutable record of user actions, lead status updates, and administrative changes.</p>
        </div>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Subject</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                <tr>
                    <td><span style="font-size: 12px; color: var(--or-slate);">{{ $log->created_at->format('M j, Y - H:i:s') }}</span></td>
                    <td><strong>{{ $log->actor ? $log->actor->name : 'System' }}</strong></td>
                    <td><span class="or-badge badge-blue">{{ $log->action }}</span></td>
                    <td>{{ $log->subject_type ? class_basename($log->subject_type) . " #{$log->subject_id}" : 'Global' }}</td>
                    <td><span style="font-family: monospace; font-size: 11px;">{{ $log->ip ?? '127.0.0.1' }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $logs->links() }}
        </div>
    </div>
</x-layouts.app>
