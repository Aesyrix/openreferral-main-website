@props(['status'])

@php
    $status = $status instanceof \App\Enums\UserStatus
        ? $status
        : \App\Enums\UserStatus::tryFrom((string) $status);
@endphp

@if($status)
    <span class="or-badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
@else
    <span class="or-badge badge-slate">Unknown</span>
@endif
