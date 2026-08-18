@props(['status'])

@php
    $status = $status instanceof \App\Enums\LeadStatus
        ? $status
        : \App\Enums\LeadStatus::tryFrom((string) $status);
@endphp

@if($status)
    <span class="or-badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
@else
    <span class="or-badge badge-slate">Unknown</span>
@endif
