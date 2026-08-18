<x-layouts.app title="Support Desk — Agent Partner">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Support & Operations Desk</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Open a ticket with OpenReferral staff for lead questions, replacement requests, or billing help.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
        <div class="or-card">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px 0; color: #0B1B2B;">Submit Support Ticket</h3>
            <form action="{{ route('agent.support.store') }}" method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                @csrf
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Subject</label>
                    <input type="text" name="subject" required placeholder="e.g. Lead Replacement Request" style="width: 100%; padding: 8px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Priority</label>
                    <select name="priority" required style="width: 100%; padding: 8px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px;">
                        <option value="normal">Normal</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Ticket Message</label>
                    <textarea name="body" required rows="4" placeholder="Describe your inquiry or lead issue in detail..." style="width: 100%; padding: 8px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px; box-sizing: border-box;"></textarea>
                </div>
                <button type="submit" class="or-btn or-btn-primary">Submit Ticket &rarr;</button>
            </form>
        </div>

        <div class="or-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--or-line); font-weight: 700; color: #0B1B2B;">
                My Open & Closed Tickets
            </div>
            <table class="or-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $tick)
                    <tr>
                        <td>#{{ $tick->id }}</td>
                        <td><strong>{{ $tick->subject }}</strong></td>
                        <td><span class="or-badge badge-blue">{{ ucfirst($tick->status) }}</span></td>
                        <td>{{ $tick->created_at->format('M j, Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="color: var(--or-slate);">No support tickets submitted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
