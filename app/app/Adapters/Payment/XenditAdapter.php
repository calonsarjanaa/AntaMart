<?php

namespace App\Adapters\Payment;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class XenditAdapter implements PaymentGatewayInterface
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $webhookToken = null,
        protected ?string $baseUrl = null,
        protected ?array $paymentMethods = null,
        protected ?int $invoiceDuration = null
    ) {
        $hasConfig = function_exists('config') && function_exists('app') && app()->has('config');
        $this->secretKey = $secretKey ?? ($hasConfig ? config('services.xendit.secret_key') : null);
        $this->webhookToken = $webhookToken ?? ($hasConfig ? config('services.xendit.webhook_token') : null);
        $this->baseUrl = $baseUrl ?? ($hasConfig ? config('services.xendit.base_url', 'https://api.xendit.co') : 'https://api.xendit.co');
        $this->paymentMethods = $paymentMethods ?? ($hasConfig ? config('services.xendit.payment_methods', ['QRIS']) : ['QRIS']);
        $this->invoiceDuration = $invoiceDuration ?? ($hasConfig ? (int) config('services.xendit.invoice_duration', 3600) : 3600);
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

        $duration = max(60, (int) ($metadata['invoice_duration'] ?? $this->invoiceDuration ?? 3600));

        $payload = [
            'external_id'      => $orderId,
            'amount'           => (int) round($amount),
            'payer_email'      => $customerEmail,
            'description'      => "AntaMart Order #{$orderId}",
            'invoice_duration' => $duration, // 60 minutes (3600s) default after store confirmation
            'currency'         => 'IDR',
        ];

        $paymentMethods = $metadata['payment_methods'] ?? $this->paymentMethods;
        if (!empty($paymentMethods)) {
            $payload['payment_methods'] = array_values($paymentMethods);
        }

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

    /**
     * Fetch the latest invoice status directly from Xendit API.
     *
     * @param string $invoiceId
     * @return array<string, mixed>|null
     */
    public function getInvoice(string $invoiceId): ?array
    {
        if (empty($this->secretKey) || empty($invoiceId)) {
            return null;
        }

        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(10)
            ->get("{$this->baseUrl}/v2/invoices/{$invoiceId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Best-effort expire an active Xendit invoice so the payment link cannot be used after cancellation.
     */
    public function expireInvoice(string $invoiceId): bool
    {
        if (empty($this->secretKey) || empty($invoiceId)) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(10)
                ->post("{$this->baseUrl}/invoices/{$invoiceId}/expire!");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
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
            'amount'     => (float) ($data['paid_amount'] ?? $data['amount'] ?? 0),
            'raw'        => $payload,
        ];
    }
}
