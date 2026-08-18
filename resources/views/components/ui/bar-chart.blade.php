@props([
    'series' => [],          // [['date' => '2026-08-01', 'count' => 3], ...]
    'valueKey' => 'count',
    'height' => 150,
    'color' => 'var(--or-blue)',
    'format' => null,        // optional callable for the tooltip/label
])

@php
    $rows = collect($series);
    $max = max(1, (int) ceil($rows->max($valueKey) ?? 0));
@endphp

@if($rows->isEmpty())
    <x-ui.empty icon="📈" title="No data in this window" />
@else
    <div style="display: flex; align-items: flex-end; gap: 6px; height: {{ $height }}px; padding-top: 8px;">
        @foreach($rows as $row)
            @php
                $value = (float) ($row[$valueKey] ?? 0);
                $pct = $max > 0 ? max(2, round($value / $max * 100)) : 2;
                $display = $format ? $format($value) : (string) (int) $value;
            @endphp
            <div style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%;"
                 title="{{ \Illuminate\Support\Carbon::parse($row['date'])->format('D, M j') }} — {{ $display }}">
                <div style="font-size: 10px; color: var(--or-slate); margin-bottom: 3px; font-weight: 600;">
                    {{ $value > 0 ? $display : '' }}
                </div>
                <div style="width: 100%; max-width: 34px; height: {{ $pct }}%; background-color: {{ $value > 0 ? $color : 'var(--or-line)' }}; border-radius: 5px 5px 0 0;"></div>
            </div>
        @endforeach
    </div>
    <div style="display: flex; gap: 6px; margin-top: 6px; border-top: 1px solid var(--or-line); padding-top: 6px;">
        @foreach($rows as $row)
            <div style="flex: 1; text-align: center; font-size: 9px; color: var(--or-slate);">
                {{ \Illuminate\Support\Carbon::parse($row['date'])->format($rows->count() > 14 ? 'j' : 'D') }}
            </div>
        @endforeach
    </div>
@endif
