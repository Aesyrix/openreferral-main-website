@props([
    'label' => '',
    'value' => '—',
    'hint' => null,
    'accent' => 'var(--or-blue)',
    'href' => null,
])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    class="or-card or-kpi-card"
    style="border-left-color: {{ $accent }}; text-decoration: none; color: inherit; display: block;"
>
    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: var(--or-slate);">
        {{ $label }}
    </div>
    <div style="font-size: 26px; font-weight: 800; color: var(--or-ink); margin-top: 6px; line-height: 1.1;">
        {{ $value }}
    </div>
    @if(filled($hint))
        <div style="font-size: 12px; color: var(--or-slate); margin-top: 6px;">{{ $hint }}</div>
    @endif
    {{ $slot }}
</{{ $tag }}>
