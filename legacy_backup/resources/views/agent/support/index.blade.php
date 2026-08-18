<x-layouts.app title="Support Desk">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Partner Support Desk</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Open support tickets for territory modifications, billing assistance, or lead inquiries.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="or-card">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Open New Ticket</h3>
            <form action="{{ route('agent.support.store') }}" method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                @csrf
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Subject</label>
                    <input type="text" name="subject" required placeholder="Brief summary..." style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Description</label>
                    <textarea name="body" required rows="4" placeholder="Detailed explanation..." style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;"></textarea>
                </div>
                <button type="submit" class="or-btn or-btn-primary">Submit Ticket &rarr;</button>
            </form>
        </div>

        <div class="or-card">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Your Ticket History</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                @forelse($tickets as $t)
                <div style="border-bottom: 1px solid var(--or-line); padding-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong style="font-size: 13px; color: #0B1B2B;">{{ $t->subject }}</strong>
                        <span class="or-badge {{ $t->status->value === 'open' ? 'badge-amber' : 'badge-green' }}">{{ strtoupper($t->status->value) }}</span>
                    </div>
                    <div style="font-size: 12px; color: var(--or-slate); margin-top: 4px;">{{ $t->created_at->format('M j, Y') }}</div>
                </div>
                @empty
                <div style="font-size: 13px; color: var(--or-slate);">No support tickets opened yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
