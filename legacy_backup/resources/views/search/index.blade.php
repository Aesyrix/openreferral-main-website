<x-layouts.app title="Global Search Results">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Global Search Results for "{{ $query }}"</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Found {{ $leads->total() }} matching lead records.</p>
    </div>

    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Client Name</th>
                    <th>Phone</th>
                    <th>Location / Preferred Areas</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td><strong>{{ $lead->public_ref }}</strong></td>
                    <td>{{ $lead->full_name }}</td>
                    <td>{{ $lead->phone }}</td>
                    <td>{{ $lead->preferred_areas ?? ($lead->city . ', ' . $lead->state) }}</td>
                    <td><span class="or-badge badge-blue">{{ ucfirst(is_object($lead->type) ? $lead->type->value : $lead->type) }}</span></td>
                    <td><span class="or-badge {{ is_object($lead->status) ? $lead->status->badgeClass() : 'badge-blue' }}">{{ is_object($lead->status) ? $lead->status->label() : ucfirst($lead->status) }}</span></td>
                    <td>
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.leads.show', $lead->uuid) }}" class="or-btn or-btn-secondary" style="font-size: 11px; padding: 4px 8px;">View Lead</a>
                        @elseif(auth()->user()->isStaff())
                        <a href="{{ route('staff.leads.show', $lead->uuid) }}" class="or-btn or-btn-secondary" style="font-size: 11px; padding: 4px 8px;">View Lead</a>
                        @elseif(auth()->user()->isAgent())
                        <a href="{{ route('agent.leads.show', $lead->uuid) }}" class="or-btn or-btn-secondary" style="font-size: 11px; padding: 4px 8px;">View Lead</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="color: var(--or-slate); text-align: center;">No leads matched your search query.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $leads->links() }}
        </div>
    </div>
</x-layouts.app>
