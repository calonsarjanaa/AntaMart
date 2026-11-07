<?php

use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('available stock calculates physical minus reserved correctly per FR-INV-002', function () {
    $variant = ProductVariant::create([
        'product_id'     => 1,
        'sku'            => 'TEST-SKU-1',
        'name'           => 'Test Variant',
        'price'          => 15000.00,
        'stock_physical' => 10,
        'stock_reserved' => 3,
        'is_active'      => true,
    ]);

    expect($variant->available_stock)->toBe(7);

    // If reservations equal physical stock, available stock is 0
    $variant->stock_reserved = 10;
    expect($variant->available_stock)->toBe(0);

    // Should never return negative
    $variant->stock_reserved = 15;
    expect($variant->available_stock)->toBe(0);
});
