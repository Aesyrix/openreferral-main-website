<x-layouts.app title="Leads Directory">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="font-size: 22px; font-weight: 800; color: #0B1B2B; margin: 0;">Network Leads Directory</h1>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('admin.leads.create') }}" class="or-btn or-btn-primary">+ Create Lead</a>
            <a href="{{ route('leads.export') }}?format=csv" class="or-btn or-btn-secondary">Export CSV</a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="or-card" style="margin-bottom: 20px; padding: 16px;">
        <form method="GET" action="{{ route('admin.leads.index') }}" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search Ref, Name, Phone, City..." style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px; flex: 1; min-width: 200px;">

            <select name="status" style="padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 13px;">
                <option value="">-- All Statuses --</option>
                <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
                <option value="qualified" {{ request('status') === 'qualified' ? 'selected' : '' }}>Qualified</option>
                <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Assigned (Pending Accept)</option>
                <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                <option value="contacted" {{ request('status') === 'contacted' ? 'selected' : '' }}>Contacted</option>
                <option value="under_contract" {{ request('status') === 'under_contract' ? 'selected' : '' }}>Under Contract</option>
                <option value="closed_won" {{ request('status') === 'closed_won' ? 'selected' : '' }}>Closed Won</option>
                <option value="unresponsive" {{ request('status') === 'unresponsive' ? 'selected' : '' }}>Unresponsive (SLA Breach)</option>
            </select>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 8px 14px; font-size: 13px;">Filter</button>
            <a href="{{ route('admin.leads.index') }}" class="or-btn or-btn-secondary" style="padding: 8px 14px; font-size: 13px;">Reset</a>
        </form>
    </div>

    <!-- Drag and Drop Import Dropzone -->
    <div class="or-card" style="margin-bottom: 20px; padding: 16px;">
        <form action="{{ route('leads.import') }}" method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
            @csrf
            <div>
                <strong style="font-size: 14px; color: #0B1B2B;">CSV / XLSX Batch Import:</strong>
                <span style="font-size: 12px; color: var(--or-slate); margin-left: 8px;">Upload .csv or .xlsx file to import leads into unassigned queue.</span>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="file" name="file" required accept=".csv,.xlsx" style="font-size: 12px;">
                <button type="submit" class="or-btn or-btn-secondary" style="font-size: 12px; padding: 6px 12px;">Upload Import</button>
            </div>
        </form>
    </div>

    <!-- Leads Table -->
    <div class="or-card" style="padding: 0; overflow: hidden;">
        <table class="or-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Client Name</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Assigned Agent</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td><strong>{{ $lead->public_ref }}</strong></td>
                    <td>
                        <div style="font-weight: 600; color: #0B1B2B;">{{ $lead->full_name }}</div>
                        <div style="font-size: 11px; color: var(--or-slate);">{{ $lead->phone }}</div>
                    </td>
                    <td>{{ ucfirst(is_object($lead->type) ? $lead->type->value : $lead->type) }}</td>
                    <td>{{ $lead->city }}, {{ $lead->state }} {{ $lead->postal_code }}</td>
                    <td>
                        <span class="or-badge {{ is_object($lead->status) ? $lead->status->badgeClass() : 'badge-blue' }}">
                            {{ is_object($lead->status) ? $lead->status->label() : ucfirst($lead->status) }}
                        </span>
                    </td>
                    <td>{{ $lead->assignedAgent ? $lead->assignedAgent->name : 'Unassigned' }}</td>
                    <td>{{ $lead->created_at ? $lead->created_at->format('M j, Y') : '' }}</td>
                    <td>
                        <a href="{{ route('admin.leads.show', $lead->uuid) }}" class="or-btn or-btn-primary" style="padding: 4px 10px; font-size: 12px;">Manage &rarr;</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--or-slate);">No leads found matching current criteria.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div style="padding: 16px;">
            {{ $leads->links() }}
        </div>
    </div>
</x-layouts.app>
