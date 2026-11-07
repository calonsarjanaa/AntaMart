@props(['tone' => 'info'])

<div {{ $attributes->class(['alert', 'alert-' . $tone])->merge(['role' => $tone === 'error' ? 'alert' : 'status']) }}>
    {{ $slot }}
</div>
