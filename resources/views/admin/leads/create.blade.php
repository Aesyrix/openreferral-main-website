<x-layouts.app title="New Lead">
    <x-ui.page-header
        title="Create a Lead"
        subtitle="Capture the client, the property brief, and who it goes to.">
        <x-slot:actions>
            <a href="{{ route('admin.leads.index') }}" class="or-btn or-btn-secondary">← Leads</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.leads.store') }}">
        @csrf

        @include('admin.leads._form', ['lead' => null, 'agents' => $agents, 'defaultFee' => $defaultFee, 'showAssign' => true])

        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="or-btn or-btn-primary" style="padding: 11px 26px;">Create lead</button>
            <a href="{{ route('admin.leads.index') }}" class="or-btn or-btn-secondary" style="padding: 11px 26px;">Cancel</a>
        </div>
    </form>
</x-layouts.app>
