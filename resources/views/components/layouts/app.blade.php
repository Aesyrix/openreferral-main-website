@props(['title' => 'Dashboard'])

@php
    $user = auth()->user();
    $unreadCount = $user ? $user->unreadNotifications()->count() : 0;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ \App\Models\Setting::get('company_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background-color: var(--or-paper); color: var(--or-ink);">

<div style="display: flex; min-height: 100vh;">
    <aside class="or-sidebar">
        <div style="padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
            <a href="{{ url('/') }}" style="display: inline-block;">
                <img src="{{ asset('images/logo-full.png') }}" alt="{{ \App\Models\Setting::get('company_name') }}"
                     style="max-height: 46px; width: auto; display: block;">
            </a>
        </div>

        <nav style="flex: 1; padding: 14px 0; overflow-y: auto;">
            @auth
                @if($user->isStaff())
                    <div style="padding: 0 24px 8px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px;">
                        {{ $user->isAdmin() ? 'Admin' : 'Staff' }} Dashboard
                    </div>
                    <a href="{{ route('admin.dashboard') }}" class="or-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">📊 Overview</a>
                    <a href="{{ route('admin.leads.index') }}" class="or-nav-item {{ request()->routeIs('admin.leads.*') ? 'active' : '' }}">🎯 Leads</a>
                    <a href="{{ route('admin.agents.index') }}" class="or-nav-item {{ request()->routeIs('admin.agents.*') ? 'active' : '' }}">
                        🤝 Agents
                        @php($pending = \App\Models\User::query()->agents()->where('status', \App\Enums\UserStatus::PENDING)->count())
                        @if($pending > 0)
                            <span class="or-badge badge-amber" style="margin-left: auto; font-size: 10px;">{{ $pending }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.analytics.index') }}" class="or-nav-item {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">📈 Analytics</a>

                    @if($user->isAdmin())
                        <div style="padding: 16px 24px 8px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px;">Admin only</div>
                        <a href="{{ route('admin.revenue.index') }}" class="or-nav-item {{ request()->routeIs('admin.revenue.*') ? 'active' : '' }}">💰 Revenue</a>
                        <a href="{{ route('admin.staff.index') }}" class="or-nav-item {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">👥 Staff</a>
                        <a href="{{ route('admin.content.index') }}" class="or-nav-item {{ request()->routeIs('admin.content.*') ? 'active' : '' }}">📄 App Content</a>
                        <a href="{{ route('admin.settings.index') }}" class="or-nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">⚙️ Settings</a>
                        <a href="{{ route('admin.audit.index') }}" class="or-nav-item {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">📜 Audit Log</a>
                    @endif
                @elseif($user->isAgent())
                    <div style="padding: 0 24px 8px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px;">Agent Portal</div>
                    <a href="{{ route('agent.dashboard') }}" class="or-nav-item {{ request()->routeIs('agent.dashboard') ? 'active' : '' }}">⚡ My Overview</a>
                    <a href="{{ route('agent.leads.index') }}" class="or-nav-item {{ request()->routeIs('agent.leads.*') ? 'active' : '' }}">🎯 My Leads</a>
                    <a href="{{ route('agent.profile') }}" class="or-nav-item {{ request()->routeIs('agent.profile') ? 'active' : '' }}">👤 My Profile</a>
                @endif

                <div style="padding: 16px 24px 8px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px;">Account</div>
                <a href="{{ route('notifications.index') }}" class="or-nav-item {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    🔔 Notifications
                    @if($unreadCount > 0)
                        <span class="or-badge badge-red" style="margin-left: auto; font-size: 10px;">{{ $unreadCount }}</span>
                    @endif
                </a>
            @endauth
        </nav>

        @auth
            <div style="padding: 16px; border-top: 1px solid rgba(255,255,255,0.1); background-color: rgba(0,0,0,0.2);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <img src="{{ $user->avatar_url }}" alt="" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                    <div style="overflow: hidden;">
                        <div style="font-weight: 600; font-size: 13px; color: white; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">{{ $user->name }}</div>
                        <div style="font-size: 11px; color: #94A3B8;">{{ $user->role?->label() }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="width: 100%; background: none; border: 1px solid rgba(255,255,255,0.2); color: #CBD5E1; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        Sign out
                    </button>
                </form>
            </div>
        @endauth
    </aside>

    <div style="flex: 1; display: flex; flex-direction: column; min-width: 0;">
        <header class="or-topbar">
            <form action="{{ route('global.search') }}" method="GET" style="display: flex; align-items: center; gap: 8px; width: 380px; max-width: 50vw;">
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="Search leads by reference, name, phone or location…"
                       style="width: 100%; padding: 8px 14px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px; outline: none;">
            </form>

            <div style="display: flex; align-items: center; gap: 16px;">
                <a href="{{ route('notifications.index') }}" style="position: relative; text-decoration: none; font-size: 18px;">
                    🔔
                    @if($unreadCount > 0)
                        <span style="position: absolute; top: -5px; right: -8px; background-color: var(--or-danger); color: white; border-radius: 9999px; font-size: 10px; min-width: 17px; height: 17px; display: flex; align-items: center; justify-content: center; font-weight: 700; padding: 0 3px;">
                            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                        </span>
                    @endif
                </a>

                @auth
                    <span class="or-badge {{ $user->role?->badgeClass() }}">{{ strtoupper($user->role?->value) }}</span>
                @endauth
            </div>
        </header>

        <main style="flex: 1; padding: 24px; overflow-y: auto;">
            @if(session('success'))
                <div style="background-color: #DCFCE7; border: 1px solid #86EFAC; color: #15803D; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; font-weight: 500;">
                    ✓ {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div style="background-color: #FEE2E2; border: 1px solid #FCA5A5; color: #B91C1C; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; font-weight: 500;">
                    ⚠ {{ session('error') }}
                </div>
            @endif

            @if($errors->any() && $errors->count() > 0)
                <div style="background-color: #FEF3C7; border: 1px solid #FCD34D; color: #92400E; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
                    <strong>Please fix the following:</strong>
                    <ul style="margin: 6px 0 0; padding-left: 18px; line-height: 1.6;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>

</body>
</html>
