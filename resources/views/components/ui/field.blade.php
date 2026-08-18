@props([
    'label' => '',
    'name' => '',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'hint' => null,
    'options' => null,   // [value => label] renders a <select>
    'rows' => 4,         // used when type=textarea
    'step' => null,
])

@php($current = old($name, $value))

<div style="margin-bottom: 16px;">
    <label for="{{ $name }}" style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">
        {{ $label }}@if($required)<span style="color: var(--or-danger);"> *</span>@endif
    </label>

    @php($base = 'width: 100%; padding: 9px 12px; border: 1px solid '.($errors->has($name) ? 'var(--or-danger)' : '#CBD5E1').'; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit; background-color: white;')

    @if($options !== null)
        <select id="{{ $name }}" name="{{ $name }}" @required($required) style="{{ $base }}">
            @if(! $required)
                <option value="">— Select —</option>
            @endif
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
                  placeholder="{{ $placeholder }}" style="{{ $base }} resize: vertical;">{{ $current }}</textarea>
    @else
        <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $current }}"
               @required($required) placeholder="{{ $placeholder }}" @if($step) step="{{ $step }}" @endif
               style="{{ $base }}">
    @endif

    @error($name)
        <div style="color: var(--or-danger); font-size: 12px; margin-top: 4px;">{{ $message }}</div>
    @enderror

    @if(filled($hint) && ! $errors->has($name))
        <div style="color: var(--or-slate); font-size: 12px; margin-top: 4px;">{{ $hint }}</div>
    @endif
</div>
