<x-emails.layout :heading="match($state) {
        'suspended' => 'Account suspended',
        'restored' => 'Account reactivated',
        default => 'Account removed',
    }">
    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        Hi {{ $agent->name }},
    </p>

    @if($state === 'suspended')
        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
            Your Open Referral agent account has been suspended. You won't receive new
            lead assignments and your app sessions have been signed out.
        </p>
    @elseif($state === 'restored')
        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
            Your Open Referral agent account has been reactivated. Sign back in and
            you'll start receiving lead assignments again.
        </p>
    @else
        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
            Your Open Referral agent account has been removed. Any leads still open with
            you have been returned to our team for reassignment.
        </p>
    @endif

    @if($reason)
        <div style="margin:0 0 18px 0; padding:14px 16px; background-color:#F1F5F9; border-left:4px solid #5B6B7C; border-radius:6px;">
            <p style="margin:0; font-size:14px; line-height:1.6;"><strong>Note:</strong> {{ $reason }}</p>
        </div>
    @endif

    <p style="margin:0; font-size:14px; line-height:1.6; color:#5B6B7C;">
        If you have questions about this change, reply to this email and we'll help.
    </p>
</x-emails.layout>
