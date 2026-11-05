<?php

namespace App\Adapters\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create a payment session/invoice for the given order.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $customerEmail
     * @param array<string, mixed> $metadata
     * @return array{id: string, payment_url: string, expires_at: string}
     */
    public function createInvoice(
        string $orderId,
        float $amount,
        string $customerEmail,
        array $metadata = []
    ): array;

    /**
     * Verify the webhook request signature / token.
     *
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     * @return bool
     */
    public function verifyWebhook(array $headers, array $payload): bool;

    /**
     * Parse raw callback payload into standardized data structure.
     *
     * @param array<string, mixed> $payload
     * @return array{
     *     payment_id: string,
     *     order_id: string,
     *     status: string,
     *     amount: float,
     *     raw: array<string, mixed>
     * }
     */
    public function parseCallback(array $payload): array;
}

