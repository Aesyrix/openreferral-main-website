<x-layouts.app title="User Management — Admin HQ">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">User & Access Management</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Manage Admins, Staff ISAs, and Agent Partner credentials.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="or-btn or-btn-primary">+ Add New User</a>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name & Email</th>
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Created Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>#{{ $user->id }}</td>
                    <td>
                        <div style="font-weight: 700; color: #0B1B2B;">{{ $user->name }}</div>
                        <div style="font-size: 12px; color: var(--or-slate);">{{ $user->email }}</div>
                    </td>
                    <td>
                        <span class="or-badge {{ $user->isAdmin() ? 'badge-red' : ($user->isStaff() ? 'badge-amber' : 'badge-blue') }}">
                            {{ strtoupper($user->role->value) }}
                        </span>
                    </td>
                    <td>{{ $user->phone ?? 'N/A' }}</td>
                    <td>
                        <span class="or-badge badge-green">{{ ucfirst($user->status ?? 'active') }}</span>
                    </td>
                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $users->links() }}
        </div>
    </div>
</x-layouts.app>
