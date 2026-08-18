<x-layouts.app title="My Assigned Referrals">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="font-size: 22px; font-weight: 800; color: #0B1B2B; margin: 0;">My Assigned Referrals</h1>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Client Name</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td><strong>{{ $lead->public_ref }}</strong></td>
                    <td>{{ $lead->full_name }}</td>
                    <td>{{ ucfirst(is_object($lead->type) ? $lead->type->value : $lead->type) }}</td>
                    <td>{{ $lead->city }}, {{ $lead->postal_code }}</td>
                    <td>
                        <span class="or-badge {{ is_object($lead->status) ? $lead->status->badgeClass() : 'badge-blue' }}">
                            {{ is_object($lead->status) ? $lead->status->label() : ucfirst($lead->status) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('agent.leads.show', $lead->uuid) }}" class="or-btn or-btn-primary" style="padding: 4px 10px; font-size: 12px;">Manage &rarr;</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--or-slate);">No assigned referrals found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $leads->links() }}
        </div>
    </div>
</x-layouts.app>
