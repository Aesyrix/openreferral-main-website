<x-layouts.app title="Agent Partner Portal">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Welcome, {{ $agent->name }}</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Exclusive Referral Operations · {{ $profile ? $profile->brokerage : 'Brokerage Partner' }}</p>
        </div>

        <div style="display: flex; gap: 12px; align-items: center;">
            <div class="or-badge badge-teal" style="font-size: 13px; padding: 6px 12px;">
                💳 {{ $profile->complimentary_remaining ?? 0 }} Free Credits Left
            </div>
        </div>
    </div>

    <!-- PENDING ACCEPTANCE CARDS (NEEDS YOUR RESPONSE) -->
    @if($pendingLeads->count() > 0)
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 16px; font-weight: 800; color: #0B1B2B; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
            ⚡ Needs Your Immediate Response ({{ $pendingLeads->count() }})
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
            @foreach($pendingLeads as $lead)
            <div class="or-card" style="border-left: 4px solid var(--or-blue); background-color: #F8FAFC;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <div>
                        <span class="or-badge badge-blue">{{ $lead->public_ref }}</span>
                        <span style="font-size: 12px; font-weight: 700; color: #0B1B2B; margin-left: 6px;">{{ ucfirst(is_object($lead->type) ? $lead->type->value : $lead->type) }}</span>
                    </div>
                    @if($lead->accept_by_at)
                    <span class="sla-chip-amber">Accept within {{ $lead->accept_by_at->diffForHumans(['parts' => 2]) }}</span>
                    @endif
                </div>

                <h3 style="font-size: 18px; font-weight: 700; color: #0B1B2B; margin: 0 0 4px 0;">{{ $lead->full_name }}</h3>
                <div style="font-size: 13px; color: var(--or-slate); margin-bottom: 12px;">
                    📍 {{ $lead->city }}, {{ $lead->state }} {{ $lead->postal_code }} · Budget: ${{ number_format(($lead->budget_max ?? 0)/100) }}
                </div>

                <div style="background-color: white; padding: 10px; border-radius: 8px; font-size: 12px; color: #334155; margin-bottom: 14px; border: 1px solid var(--or-line);">
                    "This exclusive lead is locked to you. Accept now to claim client contact details."
                </div>

                <div style="display: flex; gap: 10px;">
                    <form action="{{ route('leads.accept', $lead->uuid) }}" method="POST" style="flex: 1;">
                        @csrf
                        <button type="submit" class="or-btn or-btn-primary" style="width: 100%; padding: 8px;">✓ Accept Referral</button>
                    </form>

                    <!-- Reject Modal Trigger -->
                    <a href="{{ route('agent.leads.show', $lead->uuid) }}" class="or-btn or-btn-secondary" style="flex: 1; padding: 8px; text-align: center;">View / Reject</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Personal Stats Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="or-card or-kpi-card">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600;">ACTIVE REFERRALS</div>
            <div style="font-size: 26px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $activeLeads->count() }}</div>
        </div>
        <div class="or-card or-kpi-card" style="border-left-color: var(--or-teal);">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600;">CONTACTED RATE</div>
            <div style="font-size: 26px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $contactedPct }}%</div>
        </div>
        <div class="or-card or-kpi-card" style="border-left-color: var(--or-success);">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600;">CLOSED WON DEALS</div>
            <div style="font-size: 26px; font-weight: 800; color: var(--or-success); margin-top: 4px;">{{ $closedCount }}</div>
        </div>
        <div class="or-card or-kpi-card" style="border-left-color: #7C3AED;">
            <div style="font-size: 12px; color: var(--or-slate); font-weight: 600;">ESTIMATED COMMISSION</div>
            <div style="font-size: 26px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">${{ number_format($estimatedCommission, 0) }}</div>
        </div>
    </div>

    <!-- Active Referrals List -->
    <div class="or-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px; border-bottom: 1px solid var(--or-line); font-size: 16px; font-weight: 700; color: #0B1B2B;">
            Active Referral Pipeline
        </div>
        <table class="or-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Client Name</th>
                    <th>Phone / Email</th>
                    <th>Location</th>
                    <th>Current Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activeLeads as $lead)
                <tr>
                    <td><strong>{{ $lead->public_ref }}</strong></td>
                    <td>{{ $lead->full_name }}</td>
                    <td><a href="tel:{{ $lead->phone }}" style="color: var(--or-blue); text-decoration: none;">{{ $lead->phone }}</a></td>
                    <td>{{ $lead->city }}, {{ $lead->postal_code }}</td>
                    <td>
                        <span class="or-badge {{ is_object($lead->status) ? $lead->status->badgeClass() : 'badge-blue' }}">
                            {{ is_object($lead->status) ? $lead->status->label() : ucfirst($lead->status) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('agent.leads.show', $lead->uuid) }}" class="or-btn or-btn-primary" style="padding: 4px 10px; font-size: 12px;">Update Progress &rarr;</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--or-slate);">No active accepted referrals. We'll notify you when a match hits your ZIPs!</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
