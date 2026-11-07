@props(['label' => 'Loading'])

<div {{ $attributes->class(['loading-state']) }} role="status" aria-live="polite" aria-busy="true">
    <span class="loading-spinner" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</div>
