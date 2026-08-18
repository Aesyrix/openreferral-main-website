<x-layouts.app title="System Settings — Admin HQ">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Global System Settings</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Configure SLA accept windows, file upload limits, and company metadata.</p>
    </div>

    <div class="or-card" style="max-width: 650px;">
        <form action="{{ route('admin.settings.update') }}" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
            @csrf

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">SLA Accept Window (Hours)</label>
                <input type="number" name="sla_hours" value="{{ $settings['sla_hours'] ?? 4 }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                <span style="font-size: 11px; color: var(--or-slate);">Time window allowed for agents to accept an assigned lead before SLA breach.</span>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Stale Lead Threshold (Days)</label>
                <input type="number" name="stale_days" value="{{ $settings['stale_days'] ?? 5 }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                <span style="font-size: 11px; color: var(--or-slate);">Days of agent inactivity before a lead is flagged as stale.</span>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Max File Upload Size (MB)</label>
                <input type="number" name="max_upload_mb" value="{{ $settings['max_upload_mb'] ?? 15 }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Company / Entity Name</label>
                <input type="text" name="company_name" value="{{ $settings['company_name'] ?? 'Open Referral / ONE STOP SHOP' }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">License Number & State</label>
                <input type="text" name="license_no" value="{{ $settings['license_no'] ?? 'Florida License L24000235109' }}" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 10px; font-size: 14px; margin-top: 8px;">Save Settings &rarr;</button>
        </form>
    </div>
</x-layouts.app>
