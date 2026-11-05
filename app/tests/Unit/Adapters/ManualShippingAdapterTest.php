<?php

use App\Adapters\Shipping\ManualShippingAdapter;

test('manual shipping adapter indicates manual tracking requirement', function () {
    $adapter = new ManualShippingAdapter(flatRate: 15000.0);

    expect($adapter->isManual())->toBeTrue()
        ->and($adapter->calculateCost([], []))->toBe(15000.0);
});

