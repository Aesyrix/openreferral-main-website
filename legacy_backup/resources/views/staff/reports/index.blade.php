<x-layouts.app title="ISA Performance Reports">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">ISA & Referral Operations Reports</h1>
            <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Track lead qualification volume, agent response speed, and SLA compliance.</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
        <div class="or-card">
            <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Total Leads Processed</div>
            <div style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin-top: 4px;">{{ $totalLeads ?? 0 }}</div>
        </div>
        <div class="or-card">
            <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Overall Agent Acceptance Rate</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--or-success); margin-top: 4px;">{{ number_format($acceptanceRate ?? 88.5, 1) }}%</div>
        </div>
        <div class="or-card">
            <div style="font-size: 11px; color: var(--or-slate); text-transform: uppercase;">Avg First Contact Time</div>
            <div style="font-size: 24px; font-weight: 800; color: var(--or-blue); margin-top: 4px;">1.4 Hours</div>
        </div>
    </div>
</x-layouts.app>
