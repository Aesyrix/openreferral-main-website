{{-- Shared lead fields for create + edit. Expects $lead (nullable), $agents. --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 22px;">
    <div class="or-card">
        <h2 style="font-size: 14px; font-weight: 700; margin: 0 0 14px 0; text-transform: uppercase; letter-spacing: 0.5px; color: var(--or-slate);">Client</h2>

        <x-ui.field label="Full name" name="name" :value="$lead?->name" required placeholder="e.g. Melad Yousef" />
        <x-ui.field label="Phone" name="phone" type="tel" :value="$lead?->phone" placeholder="7034169995" />
        <x-ui.field label="Email" name="email" type="email" :value="$lead?->email" placeholder="client@example.com" />

        <x-ui.field
            label="Intent" name="intent" required
            :value="$lead?->intent?->value"
            :options="['buying' => 'Buying', 'selling' => 'Selling']" />

        <x-ui.field label="Source" name="source" :value="$lead?->source" placeholder="Website, Referral, Manual ISA…" />
    </div>

    <div class="or-card">
        <h2 style="font-size: 14px; font-weight: 700; margin: 0 0 14px 0; text-transform: uppercase; letter-spacing: 0.5px; color: var(--or-slate);">Property</h2>

        <x-ui.field label="Location" name="location" :value="$lead?->location" required
                    placeholder="Springfield, Manassas, Virginia" hint="Shown to the agent as the lead's area." />
        <x-ui.field label="Property type" name="property_type" :value="$lead?->property_type" placeholder="Single Family Home" />

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <x-ui.field label="Budget min ($)" name="price_min" type="number" :value="$lead?->price_min" placeholder="300000" />
            <x-ui.field label="Budget max ($)" name="price_max" type="number" :value="$lead?->price_max" placeholder="400000" />
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
            <x-ui.field label="Beds" name="bedrooms" :value="$lead?->bedrooms" placeholder="3" />
            <x-ui.field label="Baths" name="bathrooms" :value="$lead?->bathrooms" placeholder="2" />
            <x-ui.field label="Timeline" name="timeline" :value="$lead?->timeline" placeholder="ASAP" />
        </div>
    </div>

    <div class="or-card">
        <h2 style="font-size: 14px; font-weight: 700; margin: 0 0 14px 0; text-transform: uppercase; letter-spacing: 0.5px; color: var(--or-slate);">Routing & fee</h2>

        <x-ui.field
            label="Lead fee ($)" name="lead_fee_dollars" type="number" step="0.01"
            :value="$lead ? $lead->lead_fee_dollars : ($defaultFee ?? 250)"
            hint="Billed as revenue when the agent accepts this lead." />

        @if(isset($showAssign) && $showAssign)
            <x-ui.field
                label="Assign to agent (optional)" name="assign_to"
                :options="$agents->pluck('name', 'id')->all()"
                hint="Leave empty to keep it in the unassigned pool. Assigning notifies the agent immediately." />
        @endif

        <x-ui.field label="Notes for the agent" name="notes" type="textarea" :rows="7" :value="$lead?->notes"
                    placeholder="Context, qualification details, best time to call…" />
    </div>
</div>
