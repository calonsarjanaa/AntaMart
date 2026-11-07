<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('database migrations create all required tables', function () {
    $tables = [
        'users',
        'orders',
        'order_items',
        'order_addresses',
        'payments',
        'payment_callbacks',
        'inventory_reservations',
        'cancellation_requests',
        'refunds',
        'shipments',
        'audit_logs',
        'product_variants',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table [{$table}] was not created.");
    }
});

test('users table has role column', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeTrue();
});

test('orders table has required columns and indexes', function () {
    $columns = [
        'id',
        'customer_id',
        'guest_email',
        'guest_phone',
        'status',
        'subtotal',
        'shipping_cost',
        'total',
        'idempotency_key',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('orders', $column))->toBeTrue("Column [{$column}] missing from orders table.");
    }
});

test('inventory_reservations table has required columns', function () {
    $columns = [
        'id',
        'order_id',
        'product_variant_id',
        'quantity',
        'status',
        'reserved_at',
        'released_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('inventory_reservations', $column))->toBeTrue("Column [{$column}] missing from inventory_reservations.");
    }
});

test('cancellation_requests table has rejection_reason for Store Owner', function () {
    expect(Schema::hasColumn('cancellation_requests', 'rejection_reason'))->toBeTrue();
});

test('refunds table has manual tracking fields', function () {
    $columns = [
        'id',
        'order_id',
        'amount',
        'reason',
        'status',
        'refunded_by',
        'refunded_at',
        'notes',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('refunds', $column))->toBeTrue("Column [{$column}] missing from refunds table.");
    }
});
