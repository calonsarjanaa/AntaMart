<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user role helpers identify store_owner and customer correctly', function () {
    $owner = User::create([
        'name'     => 'Store Owner',
        'email'    => 'owner@example.com',
        'password' => bcrypt('password'),
        'role'     => 'store_owner',
    ]);

    $customer = User::create([
        'name'     => 'Customer',
        'email'    => 'customer@example.com',
        'password' => bcrypt('password'),
        'role'     => 'customer',
    ]);

    expect($owner->isStoreOwner())->toBeTrue()
        ->and($owner->isCustomer())->toBeFalse()
        ->and($customer->isStoreOwner())->toBeFalse()
        ->and($customer->isCustomer())->toBeTrue();
});

