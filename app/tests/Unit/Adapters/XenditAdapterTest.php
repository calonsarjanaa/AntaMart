<?php

use App\Adapters\Payment\XenditAdapter;

test('verifies xendit webhook with correct callback token', function () {
    $adapter = new XenditAdapter(webhookToken: 'secret-test-token');

    $valid = $adapter->verifyWebhook(['x-callback-token' => 'secret-test-token'], []);
    $invalid = $adapter->verifyWebhook(['x-callback-token' => 'wrong-token'], []);
    $missing = $adapter->verifyWebhook([], []);

    expect($valid)->toBeTrue()
        ->and($invalid)->toBeFalse()
        ->and($missing)->toBeFalse();
});

test('parses xendit callback payload accurately', function () {
    $adapter = new XenditAdapter();

    $payload = [
        'event' => 'payment.succeeded',
        'data' => [
            'id' => 'pay-12345',
            'reference_id' => 'order-abc-999',
            'status' => 'SUCCEEDED',
            'amount' => 175000,
        ],
    ];

    $parsed = $adapter->parseCallback($payload);

    expect($parsed['payment_id'])->toBe('pay-12345')
        ->and($parsed['order_id'])->toBe('order-abc-999')
        ->and($parsed['status'])->toBe('SUCCEEDED')
        ->and($parsed['amount'])->toBe(175000.0);
});

