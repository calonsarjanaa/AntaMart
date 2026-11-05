<?php

namespace App\Adapters\Payment;

class XenditAdapter implements PaymentGatewayInterface
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $webhookToken = null
    ) {
        $this->secretKey = $secretKey ?? (function_exists('config') && function_exists('app') && app()->has('config') ? config('services.xendit.secret_key') : null);
        $this->webhookToken = $webhookToken ?? (function_exists('config') && function_exists('app') && app()->has('config') ? config('services.xendit.webhook_token') : null);
    }

    public function createInvoice(
        string $orderId,
        float $amount,
        string $customerEmail,
        array $metadata = []
    ): array {
        // Concrete implementation with HTTP client will be completed in Task T-05
        return [
            'id' => 'stub-invoice-' . $orderId,
            'payment_url' => 'https://checkout-staging.xendit.co/web/' . $orderId,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];
    }

    public function verifyWebhook(array $headers, array $payload): bool
    {
        $token = $headers['x-callback-token'] ?? null;
        if (is_array($token)) {
            $token = $token[0] ?? null;
        }

        if (empty($token) || empty($this->webhookToken)) {
            return false;
        }

        return hash_equals($this->webhookToken, (string) $token);
    }

    public function parseCallback(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        return [
            'payment_id' => (string) ($data['id'] ?? ''),
            'order_id'   => (string) ($data['reference_id'] ?? $data['external_id'] ?? ''),
            'status'     => (string) ($data['status'] ?? ''),
            'amount'     => (float) ($data['amount'] ?? 0),
            'raw'        => $payload,
        ];
    }
}
