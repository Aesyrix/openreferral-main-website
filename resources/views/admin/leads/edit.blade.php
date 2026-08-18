<x-layouts.app title="Edit {{ $lead->reference_id }}">
    <x-ui.page-header
        title="Edit {{ $lead->reference_id }}"
        :subtitle="'Client: '.$lead->name.' · '.$lead->status?->label()">
        <x-slot:actions>
            <a href="{{ route('admin.leads.show', $lead->id) }}" class="or-btn or-btn-secondary">← Back to lead</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.leads.update', $lead->id) }}">
        @csrf @method('PUT')

        @include('admin.leads._form', ['lead' => $lead, 'agents' => $agents, 'showAssign' => false])

        <div style="display: flex; gap: 10px; margin-top: 20px; align-items: center;">
            <button type="submit" class="or-btn or-btn-primary" style="padding: 11px 26px;">Save changes</button>
            <a href="{{ route('admin.leads.show', $lead->id) }}" class="or-btn or-btn-secondary" style="padding: 11px 26px;">Cancel</a>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.leads.destroy', $lead->id) }}" style="margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--or-line);"
          onsubmit="return confirm('Delete {{ $lead->reference_id }}? It is soft-deleted, so it stays recoverable in the database.')">
        @csrf @method('DELETE')
        <button type="submit" class="or-btn or-btn-danger" style="padding: 10px 22px;">Delete this lead</button>
        <span style="font-size: 12px; color: var(--or-slate); margin-left: 10px;">Soft delete — history and feedback are preserved.</span>
    </form>
</x-layouts.app>
