<?php

namespace App\Adapters\Shipping;

class ManualShippingAdapter implements ShippingProviderInterface
{
    public function __construct(
        protected float $flatRate = 0.0
    ) {
        $this->flatRate = $flatRate ?: (float) (function_exists('config') && function_exists('app') && app()->has('config') ? config('shipping.manual_flat_rate', 0.0) : 0.0);
    }

    public function calculateCost(array $destinationAddress, array $items): float
    {
        return $this->flatRate;
    }

    public function isManual(): bool
    {
        return true;
    }
}
