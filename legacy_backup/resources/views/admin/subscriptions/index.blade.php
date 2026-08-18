<x-layouts.app title="Subscriptions — Admin HQ">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Active & Past Subscriptions</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Track agent partner subscription terms, end dates, and renewal status.</p>
        </div>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>Subscription UUID</th>
                    <th>Agent Partner</th>
                    <th>Package</th>
                    <th>Billing</th>
                    <th>Status</th>
                    <th>Starts At</th>
                    <th>Ends At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subscriptions as $sub)
                <tr>
                    <td><span style="font-family: monospace; font-size: 11px;">{{ substr($sub->uuid, 0, 8) }}...</span></td>
                    <td>
                        <div style="font-weight: 700; color: #0B1B2B;">{{ $sub->agent->name ?? 'N/A' }}</div>
                        <div style="font-size: 12px; color: var(--or-slate);">{{ $sub->agent->email ?? '' }}</div>
                    </td>
                    <td><span class="or-badge badge-blue">{{ $sub->package->name ?? 'Package' }}</span></td>
                    <td>${{ number_format(($sub->price_cents_at_purchase ?? 0)/100, 2) }} / {{ $sub->billing_cycle }}</td>
                    <td><span class="or-badge badge-green">{{ strtoupper(is_object($sub->status) ? $sub->status->value : $sub->status) }}</span></td>
                    <td>{{ $sub->starts_at ? $sub->starts_at->format('M j, Y') : 'N/A' }}</td>
                    <td><strong>{{ $sub->ends_at ? $sub->ends_at->format('M j, Y') : 'N/A' }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $subscriptions->links() }}
        </div>
    </div>
</x-layouts.app>
