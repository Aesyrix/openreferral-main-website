<x-layouts.app title="Add staff">
    <x-ui.page-header
        title="Add a dashboard member"
        subtitle="They receive an email invite with a temporary password.">
        <x-slot:actions>
            <a href="{{ route('admin.staff.index') }}" class="or-btn or-btn-secondary">← Staff</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.staff.store') }}" style="max-width: 560px;">
        @csrf

        <div class="or-card">
            <x-ui.field label="Full name" name="name" required placeholder="Ethan Bennett" />
            <x-ui.field label="Email" name="email" type="email" required placeholder="ethan@openreferral.us"
                        hint="The invite goes here — it must be a real inbox." />
            <x-ui.field label="Phone" name="phone" type="tel" placeholder="+1 (305) 555-0101" />

            <x-ui.field label="Role" name="role" required :value="'staff'"
                        :options="['staff' => 'Staff — agents, leads, analytics', 'admin' => 'Admin — everything, incl. revenue & settings']" />

            <x-ui.field label="Temporary password" name="password" type="password"
                        hint="Leave blank to generate a strong one automatically." />
        </div>

        <div style="display: flex; gap: 10px; margin-top: 18px;">
            <button type="submit" class="or-btn or-btn-primary" style="padding: 11px 26px;">Create & send invite</button>
            <a href="{{ route('admin.staff.index') }}" class="or-btn or-btn-secondary" style="padding: 11px 26px;">Cancel</a>
        </div>
    </form>
</x-layouts.app>
