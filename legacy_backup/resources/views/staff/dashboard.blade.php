<x-layouts.app title="Staff Work Queues">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Staff Operations & Work Queues</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Qualify unassigned leads, assign agents, and manage SLA breach interventions.</p>
        </div>
        <a href="{{ route('staff.leads.create') }}" class="or-btn or-btn-primary">+ Create New Lead</a>
    </div>

    <!-- Queue Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
        <div class="or-card" style="border-left: 4px solid var(--or-blue);">
            <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Unassigned Pool</div>
            <div style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $unassignedCount ?? 0 }}</div>
        </div>
        <div class="or-card" style="border-left: 4px solid #F59E0B;">
            <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Pending Acceptance</div>
            <div style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $pendingCount ?? 0 }}</div>
        </div>
        <div class="or-card" style="border-left: 4px solid var(--or-danger);">
            <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">SLA Breached / Unresponsive</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--or-danger); margin-top: 4px;">{{ $slaBreachCount ?? 0 }}</div>
        </div>
        <div class="or-card" style="border-left: 4px solid var(--or-success);">
            <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Active Agent Pipeline</div>
            <div style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $activePipelineCount ?? 0 }}</div>
        </div>
    </div>

    <!-- Leads Needing Action Table -->
    <div class="or-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--or-line); font-weight: 700; color: #0B1B2B;">
            Priority Leads Needing ISA Intervention
        </div>
        <table class="or-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Client Name</th>
                    <th>Location / Areas</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Assigned Agent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($queueLeads as $lead)
                <tr>
                    <td><a href="{{ route('staff.leads.show', $lead->uuid) }}" style="color: var(--or-blue); font-weight: 700;">{{ $lead->public_ref }}</a></td>
                    <td>
                        <div style="font-weight: 700; color: #0B1B2B;">{{ $lead->full_name }}</div>
                        <div style="font-size: 11px; color: var(--or-slate);">{{ $lead->phone }}</div>
                    </td>
                    <td>{{ $lead->preferred_areas ?? ($lead->city . ', ' . $lead->state) }}</td>
                    <td><span class="or-badge badge-blue">{{ ucfirst(is_object($lead->type) ? $lead->type->value : $lead->type) }}</span></td>
                    <td><span class="or-badge {{ is_object($lead->status) ? $lead->status->badgeClass() : 'badge-blue' }}">{{ is_object($lead->status) ? $lead->status->label() : ucfirst($lead->status) }}</span></td>
                    <td>{{ $lead->assignedAgent ? $lead->assignedAgent->name : 'Unassigned' }}</td>
                    <td>
                        <a href="{{ route('staff.leads.show', $lead->uuid) }}" class="or-btn or-btn-primary" style="font-size: 11px; padding: 4px 8px;">Process Lead &rarr;</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="color: var(--or-slate);">No urgent queue items at this time.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
