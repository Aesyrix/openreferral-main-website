@php($accepted = $feedback->accepted())

<x-emails.layout :heading="$agent->name.' '.($accepted ? 'accepted' : 'rejected').' a lead'">
    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        Hi {{ $recipient->name }},
    </p>

    <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6;">
        <strong>{{ $agent->name }}</strong> has
        <span style="color:{{ $accepted ? '#1F9D61' : '#D64545' }}; font-weight:700;">{{ $accepted ? 'accepted' : 'rejected' }}</span>
        lead <strong>{{ $lead->reference_id }}</strong>.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 6px 0; font-size:14px;">
        <tr>
            <td style="padding:8px 0; color:#5B6B7C; width:130px;">Client</td>
            <td style="padding:8px 0; font-weight:600;">{{ $lead->name }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Intent</td>
            <td style="padding:8px 0; font-weight:600;">{{ $lead->intent?->label() }}</td>
        </tr>
        @if($lead->location)
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Location</td>
            <td style="padding:8px 0; font-weight:600;">{{ $lead->location }}</td>
        </tr>
        @endif
        @if($lead->price_range)
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Budget</td>
            <td style="padding:8px 0; font-weight:600;">{{ $lead->price_range }}</td>
        </tr>
        @endif
        @if(! $accepted)
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Reason</td>
            <td style="padding:8px 0; font-weight:600; color:#D64545;">{{ $feedback->reasonLabel() ?? 'Not given' }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding:8px 0; color:#5B6B7C;">Responded</td>
            <td style="padding:8px 0; font-weight:600;">
                {{ $feedback->created_at?->format('M j, Y g:i A') }} UTC
                @if($lead->responseHours() !== null)
                    <span style="color:#5B6B7C; font-weight:400;">({{ $lead->responseHours() }}h after assignment)</span>
                @endif
            </td>
        </tr>
    </table>

    @if($feedback->comments)
        <div style="margin:14px 0 0 0; padding:14px 16px; background-color:#F5F8FB; border-left:4px solid #1B86E0; border-radius:6px;">
            <p style="margin:0 0 4px 0; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#5B6B7C;">Agent comments</p>
            <p style="margin:0; font-size:14px; line-height:1.6;">{{ $feedback->comments }}</p>
        </div>
    @endif

    <x-emails.button :url="$leadUrl" label="Open this lead" />

    @if(! $accepted)
        <p style="margin:0; font-size:13px; line-height:1.6; color:#5B6B7C;">
            This lead is now marked rejected — reassign it to another agent when you're ready.
        </p>
    @endif
</x-emails.layout>
