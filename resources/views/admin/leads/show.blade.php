<x-layouts.app :title="$lead->reference_id">
    <x-ui.page-header
        :title="$lead->reference_id.' · '.$lead->name"
        :subtitle="$lead->intent?->label().' · '.$lead->location.' · created '.$lead->created_at?->format('M j, Y')">
        <x-slot:actions>
            <a href="{{ route('admin.leads.index') }}" class="or-btn or-btn-secondary">← Leads</a>
            <a href="{{ route('admin.leads.edit', $lead->id) }}" class="or-btn or-btn-secondary">Edit</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px;">
        <x-ui.lead-status :status="$lead->status" />
        <span class="or-badge {{ $lead->intent?->badgeClass() }}">{{ $lead->intent?->label() }}</span>
        @if($lead->lead_fee_cents > 0)
            <span class="or-badge badge-teal">${{ number_format($lead->lead_fee_dollars, 2) }} lead fee</span>
        @endif
        @if($lead->responseHours() !== null)
            <span class="or-badge badge-slate">Responded in {{ $lead->responseHours() }}h</span>
        @endif
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 18px; align-items: start;">
        <div style="display: flex; flex-direction: column; gap: 18px; min-width: 0;">
            {{-- Client + brief --}}
            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Lead details</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; font-size: 13px;">
                    @php
                        $details = [
                            'Client' => $lead->name,
                            'Phone' => $lead->phone ?: '—',
                            'Email' => $lead->email ?: '—',
                            'Intent' => $lead->intent?->label(),
                            'Property type' => $lead->property_type ?: '—',
                            'Budget' => $lead->price_range ?? '—',
                            'Location' => $lead->location ?: '—',
                            'Timeline' => $lead->timeline ?: '—',
                            'Bedrooms' => $lead->bedrooms ?: '—',
                            'Bathrooms' => $lead->bathrooms ?: '—',
                            'Source' => $lead->source ?: '—',
                            'Created by' => $lead->createdBy?->name ?? 'System',
                        ];
                    @endphp
                    @foreach($details as $label => $value)
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--or-slate); font-weight: 700;">{{ $label }}</div>
                            <div style="margin-top: 3px;">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>

                @if($lead->notes)
                    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--or-line);">
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--or-slate); font-weight: 700; margin-bottom: 5px;">Notes</div>
                        <p style="font-size: 13px; line-height: 1.7; margin: 0; white-space: pre-line;">{{ $lead->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Agent feedback --}}
            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Agent feedback</h2>
                @if($lead->feedback->isEmpty())
                    <x-ui.empty icon="💬" title="No response yet"
                                message="Once the assigned agent accepts or rejects, their decision and comments appear here — and staff are emailed." />
                @else
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        @foreach($lead->feedback as $item)
                            <div style="border-left: 3px solid {{ $item->accepted() ? 'var(--or-success)' : 'var(--or-danger)' }}; padding: 12px 14px; background-color: var(--or-paper); border-radius: 0 8px 8px 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <div style="font-weight: 600; font-size: 14px;">
                                        {{ $item->agent?->name ?? 'Agent' }}
                                        <span class="or-badge {{ $item->accepted() ? 'badge-green' : 'badge-red' }}" style="margin-left: 6px;">
                                            {{ $item->accepted() ? 'Accepted' : 'Rejected' }}
                                        </span>
                                    </div>
                                    <div style="font-size: 12px; color: var(--or-slate);">{{ $item->created_at?->format('M j, Y g:i A') }}</div>
                                </div>
                                @if($item->reason)
                                    <div style="font-size: 13px; margin-top: 6px;"><strong>Reason:</strong> {{ $item->reasonLabel() }}</div>
                                @endif
                                @if($item->comments)
                                    <p style="font-size: 13px; color: var(--or-slate); margin: 6px 0 0; line-height: 1.6; font-style: italic;">“{{ $item->comments }}”</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Timeline --}}
            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Status history</h2>
                @if($lead->statusHistory->isEmpty())
                    <x-ui.empty icon="🕓" title="No transitions recorded yet" />
                @else
                    <div style="display: flex; flex-direction: column; gap: 0;">
                        @foreach($lead->statusHistory as $entry)
                            <div style="display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--or-line);">
                                <div style="font-size: 12px; color: var(--or-slate); width: 140px; flex-shrink: 0;">
                                    {{ $entry->created_at?->format('M j, g:i A') }}
                                </div>
                                <div style="font-size: 13px;">
                                    {{ $entry->fromLabel() ? $entry->fromLabel().' → ' : 'Created as ' }}<strong>{{ $entry->toLabel() }}</strong>
                                    <span style="color: var(--or-slate);">· {{ $entry->changedBy?->name ?? 'System' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Assignment history --}}
            @if($lead->assignments->isNotEmpty())
                <div class="or-card">
                    <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Assignment history</h2>
                    <div style="overflow-x: auto;">
                        <table class="or-table">
                            <thead><tr><th>Agent</th><th>Assigned by</th><th>From</th><th>Until</th></tr></thead>
                            <tbody>
                                @foreach($lead->assignments as $assignment)
                                    <tr>
                                        <td style="font-size: 13px; font-weight: 600;">{{ $assignment->agent?->name ?? '—' }}</td>
                                        <td style="font-size: 13px;">{{ $assignment->assignedBy?->name ?? 'System' }}</td>
                                        <td style="font-size: 12px; color: var(--or-slate);">{{ $assignment->assigned_at?->format('M j, Y g:i A') }}</td>
                                        <td style="font-size: 12px; color: var(--or-slate);">
                                            {{ $assignment->unassigned_at ? $assignment->unassigned_at->format('M j, Y g:i A') : 'Current' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar: assignment + status controls --}}
        <div style="display: flex; flex-direction: column; gap: 18px;">
            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Assignment</h2>

                @if($lead->assignedAgent)
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <img src="{{ $lead->assignedAgent->avatar_url }}" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div>
                            <a href="{{ route('admin.agents.show', $lead->assigned_to) }}" style="font-weight: 600; color: var(--or-blue); text-decoration: none; font-size: 14px;">
                                {{ $lead->assignedAgent->name }}
                            </a>
                            <div style="font-size: 12px; color: var(--or-slate);">
                                {{ $lead->assignedAgent->agentProfile?->brokerage ?? 'Agent' }}
                            </div>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: var(--or-slate); margin-bottom: 14px;">
                        Assigned {{ $lead->assigned_at?->format('M j, Y g:i A') }}
                        by {{ $lead->assignedBy?->name ?? 'system' }}
                    </div>
                @else
                    <p style="font-size: 13px; color: var(--or-slate); margin: 0 0 14px;">
                        Not assigned yet — pick an agent below and they'll be notified straight away.
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.leads.assign', $lead->id) }}">
                    @csrf
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                        {{ $lead->assignedAgent ? 'Reassign to' : 'Assign to' }}
                    </label>
                    <select name="agent_id" required
                            style="width: 100%; padding: 9px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px; margin-bottom: 10px;">
                        <option value="">— Select an active agent —</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected($lead->assigned_to === $agent->id)>
                                {{ $agent->name }}{{ $agent->agentProfile?->brokerage ? ' — '.$agent->agentProfile->brokerage : '' }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="or-btn or-btn-primary" style="width: 100%;">
                        {{ $lead->assignedAgent ? 'Reassign lead' : 'Assign lead' }}
                    </button>
                </form>

                @if($lead->assignedAgent)
                    <form method="POST" action="{{ route('admin.leads.unassign', $lead->id) }}" style="margin-top: 8px;"
                          onsubmit="return confirm('Return this lead to the unassigned pool?')">
                        @csrf
                        <button type="submit" class="or-btn or-btn-secondary" style="width: 100%;">Unassign</button>
                    </form>
                @endif
            </div>

            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Pipeline status</h2>
                <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 12px;">
                    Marking a lead converted notifies the agent and stamps the conversion date.
                </p>
                <form method="POST" action="{{ route('admin.leads.status', $lead->id) }}">
                    @csrf
                    <select name="status" required
                            style="width: 100%; padding: 9px 12px; border: 1px solid var(--or-line); border-radius: 8px; font-size: 13px; margin-bottom: 10px;">
                        @foreach(\App\Enums\LeadStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($lead->status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="or-btn or-btn-success" style="width: 100%;">Update status</button>
                </form>

                @if($lead->converted_at)
                    <div style="margin-top: 12px; font-size: 12px; color: var(--or-success); font-weight: 600;">
                        ✓ Converted {{ $lead->converted_at->format('M j, Y') }}
                    </div>
                @endif
            </div>

            @can('view-revenue')
                <div class="or-card">
                    <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Transactions</h2>
                    @if($lead->transactions->isEmpty())
                        <p style="font-size: 13px; color: var(--or-slate); margin: 0;">
                            No transaction recorded. A lead fee is billed when the agent accepts.
                        </p>
                    @else
                        @foreach($lead->transactions as $transaction)
                            <div style="display: flex; justify-content: space-between; font-size: 13px; padding: 7px 0; border-bottom: 1px solid var(--or-line);">
                                <div>
                                    {{ $transaction->type?->label() }}
                                    <div style="font-size: 11px; color: var(--or-slate);">{{ $transaction->created_at?->format('M j, Y') }} · {{ $transaction->status }}</div>
                                </div>
                                <strong>${{ number_format($transaction->amount_dollars, 2) }}</strong>
                            </div>
                        @endforeach
                    @endif
                </div>
            @endcan
        </div>
    </div>
</x-layouts.app>
