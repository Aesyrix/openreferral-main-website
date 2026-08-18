<x-layouts.app title="Notifications">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Notifications & Activity Alerts</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Stay up to date with new lead assignments, progress updates, and SLA reminders.</p>
    </div>

    <div class="or-card" style="max-width: 700px;">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            @forelse($notifications as $notif)
            <div style="padding: 12px; border-bottom: 1px solid var(--or-line); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 600; color: #0B1B2B; font-size: 14px;">{{ $notif->data['message'] ?? 'New notification received' }}</div>
                    <div style="font-size: 11px; color: var(--or-slate); margin-top: 2px;">{{ $notif->created_at->diffForHumans() }}</div>
                </div>
            </div>
            @empty
            <div style="text-align: center; color: var(--or-slate); padding: 24px;">
                ✓ You have no unread notifications!
            </div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
