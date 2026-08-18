<x-layouts.app title="Revenue">
    <x-ui.page-header
        title="Revenue"
        subtitle="Total revenue is the sum of completed transactions. Lead fees are billed when an agent accepts.">
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.revenue.index') }}">
                <select name="range" onchange="this.form.submit()"
                        style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                    @foreach(['week' => 'Last 7 days', 'fortnight' => 'Last 14 days', 'month' => 'Last 30 days', 'quarter' => 'Last 90 days', 'year' => 'Last 365 days'] as $value => $label)
                        <option value="{{ $value }}" @selected($range === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 22px;">
        <x-ui.stat label="Total revenue" value="${{ number_format($totalCents / 100, 2) }}" accent="var(--or-teal)"
                   hint="All completed transactions" />
        <x-ui.stat label="This month" value="${{ number_format($monthCents / 100, 2) }}" accent="var(--or-success)" />
        @foreach($byType as $type => $cents)
            <x-ui.stat :label="\App\Enums\TransactionType::tryFrom($type)?->label() ?? $type"
                       value="${{ number_format($cents / 100, 2) }}" accent="var(--or-blue)" />
        @endforeach
    </div>

    <div class="or-card" style="margin-bottom: 22px;">
        <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 2px 0;">Revenue over time</h2>
        <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 8px;">Completed transactions per day, in dollars.</p>
        <x-ui.bar-chart :series="$series" valueKey="cents" color="var(--or-teal)"
                        :format="fn ($cents) => '$'.number_format($cents / 100)" />
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 22px;">
        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Top earning agents</h2>
            @if($topAgents->isEmpty())
                <x-ui.empty icon="💰" title="No revenue recorded yet"
                            message="A lead fee transaction is created the moment an agent accepts a lead." />
            @else
                <table class="or-table">
                    <thead><tr><th>Agent</th><th>Transactions</th><th style="text-align: right;">Revenue</th></tr></thead>
                    <tbody>
                        @foreach($topAgents as $row)
                            <tr>
                                <td style="font-weight: 600;">
                                    <a href="{{ $row->agent ? route('admin.agents.show', $row->agent_id) : '#' }}" style="color: var(--or-blue); text-decoration: none;">
                                        {{ $row->agent?->name ?? 'Removed agent' }}
                                    </a>
                                </td>
                                <td>{{ $row->txn_count }}</td>
                                <td style="text-align: right; font-weight: 700;">${{ number_format($row->total_cents / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="or-card">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 4px 0;">Record a transaction</h2>
            <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 14px;">
                For subscriptions, manual fees, refunds, or corrections. Use a negative amount for a refund.
            </p>

            <form method="POST" action="{{ route('admin.revenue.store') }}">
                @csrf
                <x-ui.field label="Agent" name="agent_id" required
                            :options="\App\Models\User::query()->agents()->orderBy('name')->pluck('name', 'id')->all()" />

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <x-ui.field label="Amount ($)" name="amount_dollars" type="number" step="0.01" required placeholder="250.00" />
                    <x-ui.field label="Type" name="type" required :value="'lead_fee'"
                                :options="['lead_fee' => 'Lead fee', 'subscription' => 'Subscription', 'adjustment' => 'Adjustment']" />
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <x-ui.field label="Status" name="status" required :value="'completed'"
                                :options="['completed' => 'Completed', 'pending' => 'Pending', 'refunded' => 'Refunded', 'failed' => 'Failed']" />
                    <x-ui.field label="External reference" name="reference" placeholder="Stripe id (optional)" />
                </div>

                <button type="submit" class="or-btn or-btn-primary" style="width: 100%;">Record transaction</button>
            </form>
        </div>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 20px 12px; gap: 12px; flex-wrap: wrap;">
            <h2 style="font-size: 15px; font-weight: 700; margin: 0;">Transactions</h2>
            <form method="GET" action="{{ route('admin.revenue.index') }}" style="display: flex; gap: 8px;">
                <input type="hidden" name="range" value="{{ $range }}">
                <select name="status" onchange="this.form.submit()"
                        style="padding: 7px 10px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px;">
                    <option value="">All statuses</option>
                    @foreach(['completed', 'pending', 'refunded', 'failed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if($transactions->isEmpty())
            <x-ui.empty icon="🧾" title="No transactions to show" />
        @else
            <div style="overflow-x: auto;">
                <table class="or-table">
                    <thead>
                        <tr><th>Date</th><th>Agent</th><th>Lead</th><th>Type</th><th>Reference</th><th>Status</th><th style="text-align: right;">Amount</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            <tr>
                                <td style="font-size: 12px; color: var(--or-slate); white-space: nowrap;">{{ $transaction->created_at?->format('M j, Y') }}</td>
                                <td style="font-size: 13px;">{{ $transaction->agent?->name ?? '—' }}</td>
                                <td style="font-size: 13px;">
                                    @if($transaction->lead)
                                        <a href="{{ route('admin.leads.show', $transaction->lead_id) }}" style="color: var(--or-blue); text-decoration: none;">
                                            {{ $transaction->lead->reference_id }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="font-size: 13px;">{{ $transaction->type?->label() }}</td>
                                <td style="font-size: 12px; color: var(--or-slate);">{{ $transaction->reference ?? '—' }}</td>
                                <td>
                                    @php($classes = ['completed' => 'badge-green', 'pending' => 'badge-amber', 'refunded' => 'badge-slate', 'failed' => 'badge-red'])
                                    <span class="or-badge {{ $classes[$transaction->status] ?? 'badge-slate' }}">{{ ucfirst($transaction->status) }}</span>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: {{ $transaction->amount_cents < 0 ? 'var(--or-danger)' : 'var(--or-ink)' }};">
                                    ${{ number_format($transaction->amount_dollars, 2) }}
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" action="{{ route('admin.revenue.status', $transaction->id) }}" style="display: flex; gap: 5px; justify-content: flex-end;">
                                        @csrf
                                        <select name="status" style="padding: 4px 6px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 11px;">
                                            @foreach(['completed', 'pending', 'refunded', 'failed'] as $status)
                                                <option value="{{ $status }}" @selected($transaction->status === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="or-btn or-btn-secondary" style="padding: 4px 8px; font-size: 11px;">Set</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: 14px 16px;">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-layouts.app>
