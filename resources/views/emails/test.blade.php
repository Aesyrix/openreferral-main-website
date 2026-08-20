<x-emails.layout heading="Email delivery is working">
    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        Hi {{ $recipient->name }},
    </p>

    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        This is a test message from the Open Referral admin dashboard. If you are
        reading it in your inbox, your SMTP settings are correct and the system can
        now deliver:
    </p>

    <ul style="margin:0 0 20px 0; padding-left:20px; font-size:15px; line-height:1.8;">
        <li>Signup verification codes for new agents</li>
        <li>Password reset codes</li>
        <li>Approval, rejection and suspension notices</li>
        <li>Lead accept/reject alerts to staff and admins</li>
    </ul>

    <p style="margin:0; font-size:13px; line-height:1.6; color:#5B6B7C;">
        Sent {{ $sentAt }}. No action is needed — you can delete this email.
    </p>
</x-emails.layout>
