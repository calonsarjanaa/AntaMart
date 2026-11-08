<?php

namespace App\Adapters\Payment;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class XenditAdapter implements PaymentGatewayInterface
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $webhookToken = null,
        protected ?string $baseUrl = null
    ) {
        $hasConfig = function_exists('config') && function_exists('app') && app()->has('config');
        $this->secretKey = $secretKey ?? ($hasConfig ? config('services.xendit.secret_key') : null);
        $this->webhookToken = $webhookToken ?? ($hasConfig ? config('services.xendit.webhook_token') : null);
        $this->baseUrl = $baseUrl ?? ($hasConfig ? config('services.xendit.base_url', 'https://api.xendit.co') : 'https://api.xendit.co');
    }

    /**
     * Create a 15-minute payment invoice on Xendit per FR-PAY-001 & FR-PAY-002.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $customerEmail
     * @param array<string, mixed> $metadata
     * @return array{id: string, payment_url: string, expires_at: string}
     * @throws RuntimeException
     */
    public function createInvoice(
        string $orderId,
        float $amount,
        string $customerEmail,
        array $metadata = []
    ): array {
        $secretKey = $this->secretKey;
        if (empty($secretKey)) {
            throw new RuntimeException("Xendit secret key is not configured.");
        }

        $payload = [
            'external_id'      => $orderId,
            'amount'           => (int) round($amount),
            'payer_email'      => $customerEmail,
            'description'      => "AntaMart Order #{$orderId}",
            'invoice_duration' => 900, // 15 minutes (in seconds) per FR-PAY-002
            'currency'         => 'IDR',
        ];

        if (!empty($metadata['phone'])) {
            $payload['customer'] = [
                'email'           => $customerEmail,
                'mobile_number'   => $metadata['phone'],
            ];
        }

        foreach (['success_redirect_url', 'failure_redirect_url'] as $redirectField) {
            if (!empty($metadata[$redirectField])) {
                $payload[$redirectField] = $metadata[$redirectField];
            }
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->timeout(15)
            ->post("{$this->baseUrl}/v2/invoices", $payload);

        if (!$response->successful()) {
            $errorMessage = $response->json('message') ?? $response->body() ?? 'Unknown Xendit error';
            throw new RuntimeException("Xendit invoice creation failed: {$errorMessage}");
        }

        $data = $response->json();

        return [
            'id'          => (string) $data['id'],
            'payment_url' => (string) $data['invoice_url'],
            'expires_at'  => (string) ($data['expiry_date'] ?? now()->addMinutes(15)->toIso8601String()),
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
