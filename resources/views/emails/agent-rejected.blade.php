<x-emails.layout heading="Your application status">
    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        Hi {{ $agent->name }},
    </p>

    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        Thank you for your interest in Open Referral. After reviewing your application,
        we're not able to approve your agent account at this time.
    </p>

    @if($reason)
        <div style="margin:0 0 18px 0; padding:14px 16px; background-color:#FEF3C7; border-left:4px solid #E0A106; border-radius:6px;">
            <p style="margin:0; font-size:14px; line-height:1.6;">
                <strong>Reason given:</strong> {{ $reason }}
            </p>
        </div>
    @endif

    <p style="margin:0; font-size:14px; line-height:1.6; color:#5B6B7C;">
        If you believe this was a mistake, or your circumstances change, reply to this
        email and our team will take another look.
    </p>
</x-emails.layout>
