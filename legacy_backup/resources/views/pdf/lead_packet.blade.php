<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lead Packet {{ $lead->public_ref }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #122033; font-size: 13px; line-height: 1.4; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #1B86E0; padding-bottom: 15px; margin-bottom: 20px; }
        .ref-badge { float: right; background-color: #E8F3FC; color: #1B86E0; padding: 6px 12px; font-weight: bold; border-radius: 6px; font-size: 14px; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 15px; font-weight: bold; color: #0B1B2B; border-bottom: 1px solid #D7E2EC; padding-bottom: 5px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #E8F3FC; }
        th { background-color: #F5F8FB; color: #5B6B7C; font-size: 11px; text-transform: uppercase; }
        .footer { margin-top: 40px; border-top: 1px solid #D7E2EC; padding-top: 15px; font-size: 10px; color: #5B6B7C; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <span class="ref-badge">{{ $lead->public_ref }}</span>
        <img src="{{ public_path('images/logo-full.png') }}" style="max-height: 48px; width: auto;" alt="OpenReferral ONE STOP SHOP">
    </div>

    <div class="section">
        <div class="section-title">Lead Specifications & Contact</div>
        <table>
            <tr>
                <th>Client Name</th>
                <td>{{ $lead->full_name }}</td>
                <th>Client Type</th>
                <td>{{ ucfirst(is_object($lead->type) ? $lead->type->value : $lead->type) }}</td>
            </tr>
            <tr>
                <th>Phone</th>
                <td>{{ $lead->phone }}</td>
                <th>Email</th>
                <td>{{ $lead->email ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Location</th>
                <td>{{ $lead->city }}, {{ $lead->state }} {{ $lead->postal_code }}</td>
                <th>Status</th>
                <td><strong>{{ ucfirst(is_object($lead->status) ? $lead->status->value : $lead->status) }}</strong></td>
            </tr>
            <tr>
                <th>Budget Range</th>
                <td>${{ number_format(($lead->budget_min ?? 0)/100) }} - ${{ number_format(($lead->budget_max ?? 0)/100) }}</td>
                <th>Timeline</th>
                <td>{{ $lead->timeline }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Assignment & Referral Fee Details</div>
        <table>
            <tr>
                <th>Assigned Agent</th>
                <td>{{ $lead->assignedAgent ? $lead->assignedAgent->name : 'Unassigned' }}</td>
                <th>Assigned By</th>
                <td>{{ $lead->assignedBy ? $lead->assignedBy->name : 'System' }}</td>
            </tr>
            <tr>
                <th>Referral Fee %</th>
                <td>{{ $lead->referral_fee_pct ?? 20.0 }}%</td>
                <th>Exclusivity Lock</th>
                <td>{{ $lead->accepted_at ? $lead->accepted_at->format('M j, Y H:i') : 'Pending Accept' }}</td>
            </tr>
        </table>
    </div>

    @if($lead->updates->count() > 0)
    <div class="section">
        <div class="section-title">Timeline & Progress Updates</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Status Change</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lead->updates as $update)
                <tr>
                    <td>{{ $update->created_at->format('M j, Y') }}</td>
                    <td>{{ $update->user->name ?? 'System' }}</td>
                    <td>{{ $update->from_status }} &rarr; {{ $update->to_status }}</td>
                    <td>{{ $update->note }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Open Referral Operations · Florida License L24000235109 · Phone: +1 (888) 568-1757 · Email: info@openreferral.us<br>
        Confidential Lead Packet generated on {{ $generatedAt }}
    </div>
</body>
</html>
