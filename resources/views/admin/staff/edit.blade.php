<x-layouts.app title="Edit {{ $member->name }}">
    <x-ui.page-header
        :title="'Edit '.$member->name"
        :subtitle="$member->role?->label().' · '.$member->status?->label()">
        <x-slot:actions>
            <a href="{{ route('admin.staff.index') }}" class="or-btn or-btn-secondary">← Staff</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.staff.update', $member->id) }}" style="max-width: 560px;">
        @csrf @method('PUT')

        <div class="or-card">
            <x-ui.field label="Full name" name="name" :value="$member->name" required />
            <x-ui.field label="Email" name="email" type="email" :value="$member->email" required />
            <x-ui.field label="Phone" name="phone" type="tel" :value="$member->phone" />

            <x-ui.field label="Role" name="role" required :value="$member->role?->value"
                        :options="['staff' => 'Staff — agents, leads, analytics', 'admin' => 'Admin — everything, incl. revenue & settings']" />

            <x-ui.field label="Reset password" name="password" type="password"
                        hint="Leave blank to keep the current password. Setting one signs out their API tokens." />
        </div>

        <div style="display: flex; gap: 10px; margin-top: 18px;">
            <button type="submit" class="or-btn or-btn-primary" style="padding: 11px 26px;">Save changes</button>
            <a href="{{ route('admin.staff.index') }}" class="or-btn or-btn-secondary" style="padding: 11px 26px;">Cancel</a>
        </div>
    </form>
</x-layouts.app>
