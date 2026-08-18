<x-emails.layout heading="New agent awaiting approval">
    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        Hi {{ $recipient->name }},
    </p>

    <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6;">
        A new agent just signed up and is waiting for approval before they can receive leads.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 6px 0; font-size:14px;">
        <tr>
            <td style="padding:8px 0; color:#5B6B7C; width:120px;">Name</td>
            <td style="padding:8px 0; font-weight:600;">{{ $agent->name }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Email</td>
            <td style="padding:8px 0; font-weight:600;">{{ $agent->email }}</td>
        </tr>
        @if($agent->phone)
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Phone</td>
            <td style="padding:8px 0; font-weight:600;">{{ $agent->phone }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Signed up</td>
            <td style="padding:8px 0; font-weight:600;">{{ $agent->created_at?->format('M j, Y g:i A') }} UTC</td>
        </tr>
    </table>

    <x-emails.button :url="$reviewUrl" label="Review this agent" />

    <p style="margin:0; font-size:13px; line-height:1.6; color:#5B6B7C;">
        Until you approve them, the agent can sign in but cannot be assigned any leads.
    </p>
</x-emails.layout>
