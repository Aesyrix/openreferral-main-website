@props(['url' => '#', 'label' => 'Open'])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">
    <tr>
        <td style="background-color:#1B86E0; border-radius:8px;">
            <a href="{{ $url }}" style="display:inline-block; padding:11px 22px; color:#FFFFFF; font-size:14px; font-weight:600; text-decoration:none;">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>
