<x-emails.layout heading="Your dashboard access">
    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        Hi {{ $staff->name }},
    </p>

    <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6;">
        An account has been created for you on the Open Referral
        {{ $staff->isAdmin() ? 'admin' : 'staff' }} dashboard. Sign in with the
        temporary password below and change it right away.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 4px 0; font-size:14px;">
        <tr>
            <td style="padding:8px 0; color:#5B6B7C; width:170px;">Email</td>
            <td style="padding:8px 0; font-weight:600;">{{ $staff->email }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Temporary password</td>
            <td style="padding:8px 0;">
                <code style="font-size:15px; font-weight:700; background-color:#E8F3FC; padding:4px 8px; border-radius:5px; color:#0E5FA8;">{{ $temporaryPassword }}</code>
            </td>
        </tr>
    </table>

    <x-emails.button :url="$loginUrl" label="Sign in to the dashboard" />

    <p style="margin:0; font-size:13px; line-height:1.6; color:#5B6B7C;">
        For security, don't forward this email — the password above grants access to
        agent and lead data.
    </p>
</x-emails.layout>
