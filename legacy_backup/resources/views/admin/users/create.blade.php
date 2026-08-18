<x-layouts.app title="Create User — Admin HQ">
    <div style="margin-bottom: 24px;">
        <a href="{{ route('admin.users.index') }}" class="or-btn or-btn-secondary" style="font-size: 12px; padding: 6px 12px; margin-bottom: 12px;">&larr; Back to Users</a>
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Create System User</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Provision a new Admin, Staff ISA, or Agent account.</p>
    </div>

    <div class="or-card" style="max-width: 600px;">
        <form action="{{ route('admin.users.store') }}" method="POST" style="display: flex; flex-direction: column; gap: 16px;">
            @csrf

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Full Name</label>
                <input type="text" name="name" required placeholder="e.g. Sarah Jenkins" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Email Address</label>
                <input type="email" name="email" required placeholder="e.g. sarah@example.com" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Phone Number</label>
                <input type="text" name="phone" placeholder="e.g. +1 (305) 555-0199" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Role</label>
                <select name="role" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px;">
                    <option value="agent">Agent Partner</option>
                    <option value="staff">Staff ISA / Sales</option>
                    <option value="admin">System Admin</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Password</label>
                <input type="password" name="password" required placeholder="••••••••" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 10px; font-size: 14px; margin-top: 8px;">Create User &rarr;</button>
        </form>
    </div>
</x-layouts.app>
