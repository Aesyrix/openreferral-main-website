<x-layouts.app title="Staff">
    <x-ui.page-header
        title="Staff & Admins"
        subtitle="Dashboard accounts. Staff manage agents and leads; admins also see revenue and settings.">
        <x-slot:actions>
            <a href="{{ route('admin.staff.create') }}" class="or-btn or-btn-primary">+ Add member</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="or-table">
                <thead>
                    <tr>
                        <th>Member</th><th>Role</th><th>Status</th><th>Leads created</th>
                        <th>Added by</th><th>Last login</th><th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staff as $member)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="{{ $member->avatar_url }}" alt="" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <div style="font-weight: 600;">
                                            {{ $member->name }}
                                            @if($member->id === auth()->id())
                                                <span class="or-badge badge-slate" style="font-size: 10px;">You</span>
                                            @endif
                                        </div>
                                        <div style="font-size: 12px; color: var(--or-slate);">{{ $member->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="or-badge {{ $member->role?->badgeClass() }}">{{ $member->role?->label() }}</span></td>
                            <td><x-ui.user-status :status="$member->status" /></td>
                            <td style="font-size: 13px;">{{ $member->created_leads_count }}</td>
                            <td style="font-size: 13px; color: var(--or-slate);">{{ $member->creator?->name ?? '—' }}</td>
                            <td style="font-size: 12px; color: var(--or-slate);">
                                {{ $member->last_login_at ? $member->last_login_at->diffForHumans() : 'Never' }}
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="{{ route('admin.staff.edit', $member->id) }}" class="or-btn or-btn-secondary" style="padding: 5px 10px; font-size: 12px;">Edit</a>

                                @if($member->id !== auth()->id())
                                    @if($member->status === \App\Enums\UserStatus::ACTIVE)
                                        <form method="POST" action="{{ route('admin.staff.suspend', $member->id) }}" style="display: inline;"
                                              onsubmit="return confirm('Suspend {{ $member->name }}? They lose dashboard access immediately.')">
                                            @csrf
                                            <button class="or-btn or-btn-secondary" style="padding: 5px 10px; font-size: 12px;">Suspend</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.staff.restore', $member->id) }}" style="display: inline;">
                                            @csrf
                                            <button class="or-btn or-btn-success" style="padding: 5px 10px; font-size: 12px;">Reactivate</button>
                                        </form>
                                    @endif

                                    @if(! ($member->isAdmin() && $adminCount <= 1))
                                        <form method="POST" action="{{ route('admin.staff.destroy', $member->id) }}" style="display: inline;"
                                              onsubmit="return confirm('Permanently delete {{ $member->name }}? This cannot be undone. Leads they created stay, attributed to nobody.')">
                                            @csrf @method('DELETE')
                                            <button class="or-btn or-btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding: 14px 16px;">{{ $staff->links() }}</div>
    </div>
</x-layouts.app>
