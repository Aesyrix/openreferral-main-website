<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} — OpenReferral Operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body style="background-color: var(--or-paper); color: var(--or-ink);">

    <div style="display: flex; min-height: 100vh;">
        <!-- Left Sidebar -->
        <aside class="or-sidebar">
            <div style="padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                <a href="{{ route('login') }}" style="display: inline-block;">
                    <img src="{{ asset('images/logo-full.png') }}" alt="OpenReferral ONE STOP SHOP" style="max-height: 46px; width: auto; display: block;">
                </a>
            </div>

            <nav style="flex: 1; padding: 16px 0; overflow-y: auto;">
                @auth
                    @if(auth()->user()->isAdmin())
                        <div style="padding: 0 24px 8px 24px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px;">Admin HQ</div>
                        <a href="{{ route('admin.dashboard') }}" class="or-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            📊 Operations Overview
                        </a>
                        <a href="{{ route('admin.leads.index') }}" class="or-nav-item {{ request()->routeIs('admin.leads.*') ? 'active' : '' }}">
                            🎯 All Leads Directory
                        </a>
                        <a href="{{ route('admin.agents.index') }}" class="or-nav-item {{ request()->routeIs('admin.agents.*') ? 'active' : '' }}">
                            🤝 Agent Roster
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="or-nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            👥 User Management
                        </a>
                        <a href="{{ route('admin.packages.index') }}" class="or-nav-item {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}">
                            📦 Package Catalog
                        </a>
                        <a href="{{ route('admin.subscriptions.index') }}" class="or-nav-item {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}">
                            💳 Subscriptions
                        </a>
                        <a href="{{ route('admin.reports.index') }}" class="or-nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                            📈 Revenue & Reports
                        </a>
                        <a href="{{ route('admin.settings.index') }}" class="or-nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                            ⚙️ System Settings
                        </a>
                        <a href="{{ route('admin.audit.index') }}" class="or-nav-item {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                            📜 Audit Logs
                        </a>
                    @elseif(auth()->user()->isStaff())
                        <div style="padding: 0 24px 8px 24px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px;">Staff Portal</div>
                        <a href="{{ route('staff.dashboard') }}" class="or-nav-item {{ request()->routeIs('staff.dashboard') ? 'active' : '' }}">
                            📋 Work Queues
                        </a>
                        <a href="{{ route('staff.leads.index') }}" class="or-nav-item {{ request()->routeIs('staff.leads.*') ? 'active' : '' }}">
                            🎯 Leads Command Center
                        </a>
                        <a href="{{ route('staff.leads.create') }}" class="or-nav-item {{ request()->routeIs('staff.leads.create') ? 'active' : '' }}">
                            ➕ Create New Lead
                        </a>
                        <a href="{{ route('staff.agents.index') }}" class="or-nav-item {{ request()->routeIs('staff.agents.*') ? 'active' : '' }}">
                            🤝 Agent Capacity Directory
                        </a>
                    @elseif(auth()->user()->isAgent())
                        <div style="padding: 0 24px 8px 24px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 1px;">Agent Partner</div>
                        <a href="{{ route('agent.dashboard') }}" class="or-nav-item {{ request()->routeIs('agent.dashboard') ? 'active' : '' }}">
                            ⚡ Needs Your Response
                        </a>
                        <a href="{{ route('agent.leads.index') }}" class="or-nav-item {{ request()->routeIs('agent.leads.*') ? 'active' : '' }}">
                            🎯 Active Referrals
                        </a>
                        <a href="{{ route('agent.package') }}" class="or-nav-item {{ request()->routeIs('agent.package') ? 'active' : '' }}">
                            💳 Package & Guarantee
                        </a>
                        <a href="{{ route('agent.profile') }}" class="or-nav-item {{ request()->routeIs('agent.profile') ? 'active' : '' }}">
                            👤 My Profile & ZIPs
                        </a>
                        <a href="{{ route('agent.support') }}" class="or-nav-item {{ request()->routeIs('agent.support') ? 'active' : '' }}">
                            🎫 Support Desk
                        </a>
                    @endif
                @endauth
            </nav>

            <div style="padding: 16px; border-top: 1px solid rgba(255,255,255,0.1); background-color: rgba(0,0,0,0.2);">
                @auth
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <img src="{{ auth()->user()->avatar_url }}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                    <div style="overflow: hidden;">
                        <div style="font-weight: 600; font-size: 13px; color: white; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">{{ auth()->user()->name }}</div>
                        <div style="font-size: 11px; color: #94A3B8; text-transform: capitalize;">{{ auth()->user()->role->value }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="width: 100%; background: none; border: 1px solid rgba(255,255,255,0.2); color: #CBD5E1; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        Sign Out
                    </button>
                </form>
                @endauth
            </div>
        </aside>

        <!-- Main Content Area -->
        <div style="flex: 1; display: flex; flex-direction: column; min-width: 0;">
            <!-- Topbar -->
            <header class="or-topbar">
                <form action="{{ route('global.search') }}" method="GET" style="display: flex; align-items: center; gap: 8px; width: 360px;">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Global Lead Search (OR-#####, Name, Phone, City)..." style="width: 100%; padding: 8px 14px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px; outline: none;">
                </form>

                <div style="display: flex; align-items: center; gap: 16px;">
                    <a href="{{ route('notifications.index') }}" style="position: relative; text-decoration: none; color: var(--or-slate); font-size: 18px;">
                        🔔
                        @php
                            $unreadCount = auth()->user() ? auth()->user()->unreadNotifications->count() : 0;
                        @endphp
                        @if($unreadCount > 0)
                        <span style="position: absolute; top: -4px; right: -6px; background-color: var(--or-danger); color: white; border-radius: 50%; font-size: 10px; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            {{ $unreadCount }}
                        </span>
                        @endif
                    </a>

                    @auth
                    <span class="or-badge badge-blue">
                        {{ strtoupper(auth()->user()->role->value) }}
                    </span>
                    @endauth
                </div>
            </header>

            <!-- Main Scrollable Body -->
            <main style="flex: 1; padding: 24px; overflow-y: auto;">
                @if(session('success'))
                    <div style="background-color: #DCFCE7; border: 1px solid #86EFAC; color: #15803D; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500;">
                        ✓ {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div style="background-color: #FEE2E2; border: 1px solid #FCA5A5; color: #B91C1C; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500;">
                        ⚠ {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

</body>
</html>
