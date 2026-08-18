<x-layouts.app title="My Overview">
    <x-ui.page-header
        :title="'Welcome, '.$agent->name"
        :subtitle="($agent->agentProfile?->brokerage ?? 'Agent partner').' · '.($agent->serviceAreas->pluck('area')->join(', ') ?: 'No service areas set')">
        <x-slot:actions>
            <a href="{{ route('agent.leads.index') }}" class="or-btn or-btn-primary">My leads</a>
            <a href="{{ route('agent.profile') }}" class="or-btn or-btn-secondary">Profile</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if($agent->serviceAreas->isEmpty())
        <div style="background-color: #E8F3FC; border: 1px solid #BFDDF6; color: #0E5FA8; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            Add your service areas on your <a href="{{ route('agent.profile') }}" style="color: #0E5FA8; font-weight: 600;">profile</a>
            so leads get matched to your patch.
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
        <x-ui.stat label="Assigned to me" :value="number_format($counts['total'])" :href="route('agent.leads.index')" />
        <x-ui.stat label="Needs my decision" :value="number_format($counts['new'])" accent="var(--or-warning)"
                   :href="route('agent.leads.index', ['status' => 'new'])" hint="Accept or reject these" />
        <x-ui.stat label="In progress" :value="number_format($counts['in_progress'])" accent="var(--or-info)"
                   :href="route('agent.leads.index', ['status' => 'in_progress'])" />
        <x-ui.stat label="Converted" :value="number_format($counts['converted'])" accent="var(--or-success)"
                   :hint="$conversionRate.'% conversion'" :href="route('agent.leads.index', ['status' => 'converted'])" />
        <x-ui.stat label="Avg response" :value="$avgResponseHours !== null ? $avgResponseHours.'h' : '—'" accent="var(--or-purple)" />
        <x-ui.stat label="Fees billed" value="${{ number_format($revenueCents / 100, 2) }}" accent="var(--or-teal)" />
    </div>

    @if($awaiting->isNotEmpty())
        <div class="or-card" style="margin-bottom: 22px; border-left: 4px solid var(--or-warning);">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">
                ⚡ {{ $awaiting->count() }} lead{{ $awaiting->count() === 1 ? '' : 's' }} waiting on you
            </h2>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                @foreach($awaiting as $lead)
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--or-line); border-radius: 10px; flex-wrap: wrap;">
                        <div style="min-width: 0;">
                            <div style="font-weight: 700; font-size: 14px;">{{ $lead->reference_id }} · {{ $lead->name }}</div>
                            <div style="font-size: 12px; color: var(--or-slate); margin-top: 3px;">
                                {{ $lead->intent?->label() }} · {{ $lead->location }}
                                @if($lead->price_range) · {{ $lead->price_range }} @endif
                            </div>
                            <div style="font-size: 11px; color: var(--or-slate); margin-top: 3px;">
                                Assigned {{ $lead->assigned_at?->diffForHumans() }}
                            </div>
                        </div>
                        <a href="{{ route('agent.leads.show', $lead->id) }}" class="or-btn or-btn-primary" style="padding: 7px 16px; font-size: 13px;">
                            Review & respond
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px;">
        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 2px 0;">Leads received — last 7 days</h2>
            <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 8px;">Leads assigned to you per day.</p>
            <x-ui.bar-chart :series="$trend" />
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Active pipeline</h2>
            @if($active->isEmpty())
                <x-ui.empty icon="📋" title="Nothing in progress"
                            message="Leads you accept show up here while you work them." />
            @else
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    @foreach($active as $lead)
                        <a href="{{ route('agent.leads.show', $lead->id) }}"
                           style="display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--or-line); border-radius: 8px; text-decoration: none; color: inherit;">
                            <div style="min-width: 0;">
                                <div style="font-weight: 600; font-size: 13px;">{{ $lead->reference_id }} · {{ $lead->name }}</div>
                                <div style="font-size: 12px; color: var(--or-slate);">{{ $lead->phone ?: $lead->email }}</div>
                            </div>
                            <x-ui.lead-status :status="$lead->status" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
