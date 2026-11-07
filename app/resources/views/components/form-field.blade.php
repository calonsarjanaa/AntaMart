@props(['name', 'label', 'type' => 'text', 'required' => false, 'value' => null])

<div class="form-field">
    <label class="field-label" for="{{ $name }}">
        {{ $label }}
        @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
    </label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @required($required)
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['field-input', 'field-input-invalid' => $errors->has($name)]) }}
    >
    @error($name)
        <p class="field-error" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
