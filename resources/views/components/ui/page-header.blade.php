@props(['title' => '', 'subtitle' => null])

<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 22px;">
    <div>
        <h1 style="font-size: 23px; font-weight: 800; color: var(--or-navy); margin: 0;">{{ $title }}</h1>
        @if(filled($subtitle))
            <p style="color: var(--or-slate); font-size: 13px; margin: 5px 0 0 0;">{{ $subtitle }}</p>
        @endif
    </div>

    @if(isset($actions))
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            {{ $actions }}
        </div>
    @endif
</div>
