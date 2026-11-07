@props(['status'])

<span {{ $attributes->class(['status-badge', 'status-' . \Illuminate\Support\Str::slug($status)]) }}>
    {{ \Illuminate\Support\Str::headline($status) }}
</span>
