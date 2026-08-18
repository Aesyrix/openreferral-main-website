<x-layouts.app title="Referral Lead — {{ $lead->public_ref }}">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="{{ route('agent.leads.index') }}" class="or-btn or-btn-secondary" style="padding: 6px 12px; font-size: 12px;">&larr; Back to My Referrals</a>
            <h1 style="font-size: 22px; font-weight: 800; color: #0B1B2B; margin: 0;">{{ $lead->public_ref }} — {{ $lead->full_name }}</h1>
            <span class="or-badge {{ is_object($lead->status) ? $lead->status->badgeClass() : 'badge-blue' }}">
                {{ is_object($lead->status) ? $lead->status->label() : ucfirst($lead->status) }}
            </span>
        </div>

        <div style="display: flex; gap: 10px;">
            <a href="{{ route('leads.packet.pdf', $lead->uuid) }}" class="or-btn or-btn-secondary">📄 Download PDF Packet</a>
        </div>
    </div>

    <!-- Main Specs & Progress Column -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <!-- Specs Card -->
            <div class="or-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="font-size: 16px; font-weight: 700; margin: 0; color: #0B1B2B;">Client Contact & Property Specifications</h3>
                    <span style="font-size: 11px; color: var(--or-slate); background-color: var(--or-paper); padding: 4px 8px; border-radius: 4px;">
                        Date Added: <strong>{{ $lead->created_at->format('d/m/Y H:i:s') }}</strong>
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Client Name</div>
                        <div style="font-size: 16px; font-weight: 800; color: #0B1B2B;">{{ $lead->full_name }}</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Buyer / Seller</div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--or-blue);">{{ ucfirst(is_object($lead->type) ? $lead->type->value : $lead->type) }}</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Direct Phone</div>
                        <div style="font-size: 16px; font-weight: 800; color: #0B1B2B;"><a href="tel:{{ $lead->phone }}" style="color: var(--or-blue); text-decoration: none;">{{ $lead->phone }}</a></div>
                    </div>
                </div>

                <div style="background-color: var(--or-paper); padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                    <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Preferred Areas & Address</div>
                    <div style="font-size: 14px; font-weight: 600; color: #0B1B2B;">
                        {{ $lead->preferred_areas ?? ($lead->address . ', ' . $lead->city . ', ' . $lead->state . ' ' . $lead->postal_code) }}
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Beds & Baths</div>
                        <div style="font-size: 14px; font-weight: 600; color: #0B1B2B;">{{ $lead->beds ?? '3' }} Beds / {{ $lead->baths ?? '2' }} Baths</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Budget Range</div>
                        <div style="font-size: 14px; font-weight: 600; color: #0B1B2B;">${{ number_format(($lead->budget_min ?? 30000000)/100) }} - ${{ number_format(($lead->budget_max ?? 40000000)/100) }}</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Pre-Qualified</div>
                        <div style="font-size: 14px; font-weight: 600; color: {{ $lead->prequalified ? 'var(--or-success)' : 'var(--or-slate)' }};">
                            {{ $lead->prequalified ? '✓ Yes' : 'No' }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Timeline</div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--or-danger);">{{ $lead->timeline ?? 'ASAP' }}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding-top: 12px; border-top: 1px solid var(--or-line);">
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Added By (ISA)</div>
                        <div style="font-size: 13px; font-weight: 600; color: #0B1B2B;">{{ $lead->added_by ?? 'Fay' }}</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">DNC Status</div>
                        <div style="font-size: 13px; font-weight: 600; color: {{ $lead->dnc ? 'var(--or-danger)' : 'var(--or-success)' }};">
                            {{ $lead->dnc ? '⛔ Do Not Call' : '✓ Can Contact Anytime' }}
                        </div>
                    </div>
                </div>

                @if($lead->notes_internal)
                <div style="margin-top: 16px; background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; border-radius: 4px;">
                    <div style="font-size: 11px; font-weight: 700; color: #92400E; text-transform: uppercase; margin-bottom: 2px;">ISA Notes</div>
                    <div style="font-size: 13px; color: #78350F; font-weight: 500;">{{ $lead->notes_internal }}</div>
                </div>
                @endif
            </div>

            <!-- Agent Progress Form -->
            <div class="or-card">
                <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Post Client Update / Feedback Note</h3>
                <form action="{{ route('leads.progress', $lead->uuid) }}" method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                    @csrf
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Next Status</label>
                            <select name="status" required style="width: 100%; padding: 8px 10px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px;">
                                <option value="contacted">Contacted / Spoke with client</option>
                                <option value="appointment_set">Appointment Set</option>
                                <option value="showing">Showing Properties</option>
                                <option value="under_contract">Under Contract</option>
                                <option value="closed_lost">Closed Lost</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Interaction Outcome</label>
                            <select name="outcome" required style="width: 100%; padding: 8px 10px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px;">
                                <option value="connected">Connected / Conversation</option>
                                <option value="appointment_booked">Appointment Booked</option>
                                <option value="went_under_contract">Went Under Contract</option>
                                <option value="voicemail">Left Voicemail</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Feedback & Action Notes (Required)</label>
                        <textarea name="note" required rows="3" placeholder="Enter notes from your client call or meeting..." style="width: 100%; padding: 8px 10px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px; box-sizing: border-box;"></textarea>
                    </div>

                    <button type="submit" class="or-btn or-btn-primary" style="align-self: flex-start;">Post Progress Update &rarr;</button>
                </form>
            </div>

            <!-- Timeline -->
            <div class="or-card">
                <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Vertical Activity History</h3>
                <div style="display: flex; flex-direction: column; gap: 16px; position: relative; padding-left: 20px; border-left: 2px solid var(--or-line);">
                    @forelse($lead->activities as $act)
                    <div style="position: relative;">
                        <div style="position: absolute; left: -26px; top: 0; width: 10px; height: 10px; border-radius: 50%; background-color: var(--or-blue);"></div>
                        <div style="font-size: 13px; font-weight: 600; color: #0B1B2B;">{{ $act->body }}</div>
                        <div style="font-size: 11px; color: var(--or-slate); margin-top: 2px;">
                            By {{ $act->user ? $act->user->name : 'System' }} · {{ $act->created_at->format('M j, Y - g:i A') }}
                        </div>
                    </div>
                    @empty
                    <div style="font-size: 13px; color: var(--or-slate);">No activity recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Documents Panel -->
        <div>
            <div class="or-card">
                <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0; color: #0B1B2B;">Shared Documents</h3>
                <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px;">
                    @forelse($lead->documents as $doc)
                    <div style="display: flex; justify-content: space-between; align-items: center; background-color: var(--or-paper); padding: 8px 12px; border-radius: 6px; font-size: 12px;">
                        <div>
                            <div style="font-weight: 600; color: #0B1B2B;">{{ $doc->original_filename }}</div>
                            <div style="font-size: 10px; color: var(--or-slate);">{{ round($doc->size_bytes/1024) }} KB</div>
                        </div>
                        <a href="{{ route('documents.download', $doc->uuid) }}" class="or-btn or-btn-secondary" style="padding: 4px 8px; font-size: 11px;">Download</a>
                    </div>
                    @empty
                    <div style="font-size: 12px; color: var(--or-slate);">No shared documents yet.</div>
                    @endforelse
                </div>

                <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="model_type" value="App\Models\Lead">
                    <input type="hidden" name="model_id" value="{{ $lead->id }}">
                    <input type="file" name="file" required style="font-size: 11px; margin-bottom: 8px;">
                    <button type="submit" class="or-btn or-btn-secondary" style="width: 100%; font-size: 12px; padding: 6px;">Upload File</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
