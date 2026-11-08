@props(['name', 'label', 'type' => 'text', 'required' => false, 'value' => null])

@php
    $errorKey = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $fieldId = str_replace(['[', ']'], ['_', ''], $name);
@endphp

<div class="form-field">
    <label class="field-label" for="{{ $fieldId }}">
        {{ $label }}
        @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
    </label>
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($errorKey, $value) }}"
        @required($required)
        @if ($errors->has($errorKey)) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
        {{ $attributes->class(['field-input', 'field-input-invalid' => $errors->has($errorKey)]) }}
    >
    @error($errorKey)
        <p class="field-error" id="{{ $fieldId }}-error">{{ $message }}</p>
    @enderror
</div>
