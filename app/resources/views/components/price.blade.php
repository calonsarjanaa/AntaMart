@props(['amount', 'currency' => 'Rp'])

<data class="price" value="{{ number_format((float) $amount, 2, '.', '') }}">
    {{ $currency }} {{ number_format((float) $amount, 0, ',', '.') }}
</data>
