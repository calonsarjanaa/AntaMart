<?php

use App\Adapters\Payment\XenditAdapter;
use Illuminate\Support\Facades\Http;

test('createInvoice sends correct payload to Xendit API and returns session details', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'id'          => 'inv_xendit_12345',
            'external_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_xendit_12345',
            'status'      => 'PENDING',
            'expiry_date' => '2026-11-07T10:15:00.000Z',
        ], 200),
    ]);

    $adapter = new XenditAdapter(
        secretKey: 'xnd_test_secret_key_123',
        webhookToken: 'test-token',
        baseUrl: 'https://api.xendit.co'
    );

    $result = $adapter->createInvoice(
        orderId: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        amount: 250000.00,
        customerEmail: 'customer@example.com',
        metadata: ['phone' => '08123456789']
    );

    expect($result['id'])->toBe('inv_xendit_12345')
        ->and($result['payment_url'])->toBe('https://checkout.xendit.co/web/inv_xendit_12345')
        ->and($result['expires_at'])->toBe('2026-11-07T10:15:00.000Z');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.xendit.co/v2/invoices'
            && $request['external_id'] === '01ARZ3NDEKTSV4RRFFQ69G5FAV'
            && $request['amount'] === 250000
            && $request['payer_email'] === 'customer@example.com'
            && $request['invoice_duration'] === 900 // 15 minutes per FR-PAY-002
            && $request['currency'] === 'IDR';
    });
});

test('createInvoice throws RuntimeException when Xendit API responds with error', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'error_code' => 'API_VALIDATION_ERROR',
            'message'    => 'Invalid amount',
        ], 400),
    ]);

    $adapter = new XenditAdapter(
        secretKey: 'xnd_test_key',
        baseUrl: 'https://api.xendit.co'
    );

    expect(fn () => $adapter->createInvoice('order-1', 100, 'test@test.com'))
        ->toThrow(RuntimeException::class, 'Xendit invoice creation failed: Invalid amount');
});
