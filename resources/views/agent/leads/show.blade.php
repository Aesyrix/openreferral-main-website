<x-layouts.app :title="$lead->reference_id">
    <x-ui.page-header
        :title="$lead->reference_id.' · '.$lead->name"
        :subtitle="$lead->intent?->label().' · '.$lead->location">
        <x-slot:actions>
            <a href="{{ route('agent.leads.index') }}" class="or-btn or-btn-secondary">← My leads</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px;">
        <x-ui.lead-status :status="$lead->status" />
        <span class="or-badge {{ $lead->intent?->badgeClass() }}">{{ $lead->intent?->label() }}</span>
        @if($lead->timeline)<span class="or-badge badge-slate">Timeline: {{ $lead->timeline }}</span>@endif
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 18px; align-items: start;">
        <div style="display: flex; flex-direction: column; gap: 18px; min-width: 0;">
            <div class="or-card">
                <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Client & property</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; font-size: 13px;">
                    @php
                        $details = [
                            'Client' => $lead->name,
                            'Phone' => $lead->phone ?: '—',
                            'Email' => $lead->email ?: '—',
                            'Property type' => $lead->property_type ?: '—',
                            'Budget' => $lead->price_range ?? '—',
                            'Location' => $lead->location ?: '—',
                            'Bedrooms' => $lead->bedrooms ?: '—',
                            'Bathrooms' => $lead->bathrooms ?: '—',
                            'Timeline' => $lead->timeline ?: '—',
                            'Source' => $lead->source ?: '—',
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
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--or-slate); font-weight: 700; margin-bottom: 5px;">Notes from our team</div>
                        <p style="font-size: 13px; line-height: 1.7; margin: 0; white-space: pre-line;">{{ $lead->notes }}</p>
                    </div>
                @endif

                @if($lead->status === \App\Enums\LeadStatus::IN_PROGRESS && ($lead->phone || $lead->email))
                    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--or-line); display: flex; gap: 10px; flex-wrap: wrap;">
                        @if($lead->phone)
                            <a href="tel:{{ $lead->phone }}" class="or-btn or-btn-primary">📞 Call {{ $lead->phone }}</a>
                        @endif
                        @if($lead->email)
                            <a href="mailto:{{ $lead->email }}" class="or-btn or-btn-secondary">✉️ Email client</a>
                        @endif
                    </div>
                @endif
            </div>

            @if($lead->feedback->isNotEmpty())
                <div class="or-card">
                    <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 14px 0;">Your response</h2>
                    @foreach($lead->feedback as $item)
                        <div style="border-left: 3px solid {{ $item->accepted() ? 'var(--or-success)' : 'var(--or-danger)' }}; padding: 12px 14px; background-color: var(--or-paper); border-radius: 0 8px 8px 0; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                                <span class="or-badge {{ $item->accepted() ? 'badge-green' : 'badge-red' }}">
                                    {{ $item->accepted() ? 'Accepted' : 'Rejected' }}
                                </span>
                                <span style="font-size: 12px; color: var(--or-slate);">{{ $item->created_at?->format('M j, Y g:i A') }}</span>
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

        <div>
            @if($lead->isRespondable())
                <div class="or-card" style="border-left: 4px solid var(--or-warning);">
                    <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 6px 0;">Your decision</h2>
                    <p style="font-size: 12px; color: var(--or-slate); margin: 0 0 16px; line-height: 1.6;">
                        Accepting moves this lead into your pipeline. Rejecting needs a reason so our team can
                        route it better next time.
                    </p>

                    <form method="POST" action="{{ route('agent.leads.respond', $lead->id) }}" style="margin-bottom: 14px;">
                        @csrf
                        <input type="hidden" name="decision" value="accept">
                        <textarea name="comments" rows="2" placeholder="Optional note for our team…"
                                  style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box; font-family: inherit; margin-bottom: 8px; resize: vertical;"></textarea>
                        <button type="submit" class="or-btn or-btn-success" style="width: 100%;">✓ Accept this lead</button>
                    </form>

                    <div style="border-top: 1px solid var(--or-line); padding-top: 14px;">
                        <form method="POST" action="{{ route('agent.leads.respond', $lead->id) }}">
                            @csrf
                            <input type="hidden" name="decision" value="reject">

                            <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">
                                Reason for rejecting <span style="color: var(--or-danger);">*</span>
                            </label>
                            <select name="reason" required
                                    style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; margin-bottom: 10px;">
                                <option value="">— Select a reason —</option>
                                @foreach($reasons as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <textarea name="comments" rows="2" placeholder="Anything else we should know?"
                                      style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box; font-family: inherit; margin-bottom: 8px; resize: vertical;"></textarea>

                            <button type="submit" class="or-btn or-btn-danger" style="width: 100%;"
                                    onclick="return confirm('Reject this lead? It will be routed to another agent.')">
                                ✕ Reject this lead
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="or-card">
                    <h2 style="font-size: 15px; font-weight: 700; margin: 0 0 10px 0;">Lead timeline</h2>
                    @if($lead->statusHistory->isEmpty())
                        <p style="font-size: 13px; color: var(--or-slate); margin: 0;">No transitions recorded yet.</p>
                    @else
                        @foreach($lead->statusHistory as $entry)
                            <div style="padding: 8px 0; border-bottom: 1px solid var(--or-line); font-size: 13px;">
                                <div>{{ $entry->fromLabel() ? $entry->fromLabel().' → ' : '' }}<strong>{{ $entry->toLabel() }}</strong></div>
                                <div style="font-size: 11px; color: var(--or-slate);">{{ $entry->created_at?->format('M j, Y g:i A') }}</div>
                            </div>
                        @endforeach
                    @endif

                    @if($lead->status === \App\Enums\LeadStatus::CONVERTED)
                        <div style="margin-top: 14px; padding: 12px 14px; background-color: #DCFCE7; border-radius: 8px; font-size: 13px; color: #15803D; font-weight: 600;">
                            🎉 Converted{{ $lead->converted_at ? ' on '.$lead->converted_at->format('M j, Y') : '' }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
