<?php

namespace App\Adapters\Shipping;

interface ShippingProviderInterface
{
    /**
     * Calculate or retrieve shipping rate for destination and weight/items.
     *
     * @param array<string, mixed> $destinationAddress
     * @param array<int, mixed> $items
     * @return float
     */
    public function calculateCost(array $destinationAddress, array $items): float;

    /**
     * Determine if manual tracking number entry is required.
     */
    public function isManual(): bool;
}

