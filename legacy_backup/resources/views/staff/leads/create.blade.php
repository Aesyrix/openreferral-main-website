<x-layouts.app title="Create New Lead">
    <div style="margin-bottom: 24px;">
        <a href="{{ route('staff.leads.index') }}" class="or-btn or-btn-secondary" style="font-size: 12px; padding: 6px 12px; margin-bottom: 12px;">&larr; Back to Leads</a>
        <h1 style="font-size: 24px; font-weight: 800; color: #0B1B2B; margin: 0;">Create New Referral Lead</h1>
        <p style="color: var(--or-slate); font-size: 13px; margin: 4px 0 0 0;">Enter complete client specifications, budget, preferred areas, and ISA notes.</p>
    </div>

    <div class="or-card" style="max-width: 800px;">
        <form action="{{ route('staff.leads.store') }}" method="POST" style="display: flex; flex-direction: column; gap: 18px;">
            @csrf

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">First Name</label>
                    <input type="text" name="first_name" required placeholder="e.g. Melad" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Last Name</label>
                    <input type="text" name="last_name" required placeholder="e.g. Yousef" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Client Type</label>
                    <select name="type" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px;">
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                        <option value="renter">Renter</option>
                        <option value="investor">Investor</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Phone Number</label>
                    <input type="text" name="phone" required placeholder="e.g. 7034169995" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Email (Optional)</label>
                    <input type="email" name="email" placeholder="e.g. melad@example.com" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Preferred Areas / Locations</label>
                <input type="text" name="preferred_areas" placeholder="e.g. Springfield, Merrifield, Manassas, Linton Hill, Farrs Corner, Virginia" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">City</label>
                    <input type="text" name="city" required placeholder="e.g. Springfield" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">State</label>
                    <input type="text" name="state" required value="VA" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">ZIP Code</label>
                    <input type="text" name="postal_code" required value="22150" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Bedrooms</label>
                    <input type="number" name="beds" placeholder="3" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Bathrooms</label>
                    <input type="number" step="0.5" name="baths" placeholder="2" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Min Budget ($)</label>
                    <input type="number" name="budget_min" placeholder="300000" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Max Budget ($)</label>
                    <input type="number" name="budget_max" placeholder="400000" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Timeline</label>
                    <select name="timeline" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px;">
                        <option value="ASAP">ASAP</option>
                        <option value="0_30">Within 30 Days</option>
                        <option value="30_90">30 - 90 Days</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Pre-Qualified?</label>
                    <select name="prequalified" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px;">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">DNC (Do Not Call)?</label>
                    <select name="dnc" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px;">
                        <option value="0">No (Can Call)</option>
                        <option value="1">Yes (DNC Active)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Added By (ISA)</label>
                    <input type="text" name="added_by" placeholder="e.g. Fay" style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">ISA Qualification Notes</label>
                <textarea name="notes_internal" rows="4" placeholder="e.g. He wants it ASAP. And the realtor can contact him anytime." style="width: 100%; padding: 8px 12px; border: 1px solid var(--or-line); border-radius: 6px; font-size: 14px; box-sizing: border-box;"></textarea>
            </div>

            <button type="submit" class="or-btn or-btn-primary" style="padding: 12px; font-size: 15px;">Create & Qualify Lead &rarr;</button>
        </form>
    </div>
</x-layouts.app>
