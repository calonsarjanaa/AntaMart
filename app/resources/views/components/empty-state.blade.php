@props(['title', 'description', 'symbol' => '✳'])

<section {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-symbol" aria-hidden="true">{{ $symbol }}</span>
    <h3 class="empty-state-title">{{ $title }}</h3>
    <p class="empty-state-description">{{ $description }}</p>
    @isset($footer)
        <div class="empty-state-footer">{{ $footer }}</div>
    @endisset
</section>
