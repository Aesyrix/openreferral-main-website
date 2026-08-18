<x-layouts.app title="Notifications">
    <x-ui.page-header
        title="Notifications"
        :subtitle="$unreadCount > 0 ? $unreadCount.' unread' : 'You are all caught up.'">
        <x-slot:actions>
            @if(request()->boolean('unread'))
                <a href="{{ route('notifications.index') }}" class="or-btn or-btn-secondary">Show all</a>
            @else
                <a href="{{ route('notifications.index', ['unread' => 1]) }}" class="or-btn or-btn-secondary">Unread only</a>
            @endif

            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="or-btn or-btn-primary">Mark all read</button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        @if($notifications->isEmpty())
            <x-ui.empty icon="🔔" title="No notifications"
                        message="Lead assignments, agent decisions, and account updates land here." />
        @else
            @foreach($notifications as $notification)
                <div style="display: flex; gap: 14px; padding: 16px 20px; border-bottom: 1px solid var(--or-line); background-color: {{ $notification->isRead() ? 'transparent' : '#F8FBFE' }};">
                    <div style="font-size: 22px; line-height: 1;">{{ $notification->type?->icon() }}</div>

                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap;">
                            <div style="font-weight: {{ $notification->isRead() ? '600' : '700' }}; font-size: 14px;">
                                {{ $notification->title }}
                                @if(! $notification->isRead())
                                    <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background-color: var(--or-blue); margin-left: 4px; vertical-align: middle;"></span>
                                @endif
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <span class="or-badge {{ $notification->type?->badgeClass() }}" style="font-size: 10px;">{{ $notification->type?->label() }}</span>
                                <span style="font-size: 11px; color: var(--or-slate); white-space: nowrap;">{{ $notification->created_at?->diffForHumans() }}</span>
                            </div>
                        </div>

                        <p style="font-size: 13px; color: var(--or-slate); margin: 5px 0 0; line-height: 1.6;">{{ $notification->body }}</p>

                        <div style="display: flex; gap: 10px; margin-top: 9px; align-items: center;">
                            @if($notification->lead_id)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="or-btn or-btn-primary" style="padding: 4px 12px; font-size: 12px;">Open lead</button>
                                </form>
                            @elseif(! $notification->isRead())
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="or-btn or-btn-secondary" style="padding: 4px 12px; font-size: 12px;">Mark read</button>
                                </form>
                            @endif

                            @if($notification->actor)
                                <span style="font-size: 12px; color: var(--or-slate);">by {{ $notification->actor->name }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <div style="padding: 14px 20px;">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
