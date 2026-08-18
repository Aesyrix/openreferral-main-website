@props(['icon' => '📭', 'title' => 'Nothing here yet', 'message' => null])

<div style="padding: 40px 20px; text-align: center;">
    <div style="font-size: 34px; margin-bottom: 10px;">{{ $icon }}</div>
    <div style="font-weight: 700; color: var(--or-ink); font-size: 15px;">{{ $title }}</div>
    @if(filled($message))
        <p style="color: var(--or-slate); font-size: 13px; margin: 6px auto 0; max-width: 420px; line-height: 1.6;">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
