<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;

class WhatsAppNotificationService
{
    /**
     * Normalize Indonesian phone numbers to international wa.me format (e.g. 6281234567890).
     */
    public function normalizePhone(?string $rawPhone): ?string
    {
        if (empty($rawPhone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $rawPhone);
        if (!$digits || strlen($digits) < 8) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    /**
     * Resolve target WhatsApp phone number (address phone prioritized, fallback to guest phone).
     */
    public function resolveOrderPhone(Order $order): ?string
    {
        $rawPhone = $order->address?->phone ?: $order->guest_phone;

        return $this->normalizePhone($rawPhone);
    }

    /**
     * Build dynamic WhatsApp notification metadata and wa.me URL based on current order status.
     *
     * @return array{phone: string, label: string, message: string, url: string}|null
     */
    public function buildNotification(Order $order): ?array
    {
        $order->loadMissing(['customer', 'items', 'address', 'payment', 'shipment', 'refund']);

        $phone = $this->resolveOrderPhone($order);
        if (!$phone) {
            return null;
        }

        $status = $order->status;
        $hasRefund = $order->refund !== null;
        $isLatePayment = $status === OrderStatus::Expired && $order->payment?->status === PaymentStatus::Paid;

        if ($status === OrderStatus::AwaitingConfirmation) {
            return null;
        }

        if ($status === OrderStatus::PendingPayment && empty($order->payment?->xendit_payment_url)) {
            return null;
        }

        if ($status === OrderStatus::Expired && !$hasRefund && !$isLatePayment) {
            return null;
        }

        $recipientName = $order->address?->recipient_name
            ?: $order->customer?->name
            ?: 'Pelanggan';

        $isCod = $order->payment?->payment_method === PaymentMethod::CashOnDelivery;
        $isPaid = $order->payment?->status === PaymentStatus::Paid;
        $paymentMethodLabel = $this->formatPaymentMethod($isCod, $isPaid);
        $formattedTotal = $this->formatRupiah((float) $order->total);

        [$label, $intro, $extraLines] = $this->resolveStatusContent($order, $isCod, $isPaid, $formattedTotal);

        $itemLines = $order->items->map(function ($item): string {
            $productName = $item->product_snapshot['name'] ?? 'Produk';
            $variantName = $item->variant_snapshot['name'] ?? null;
            $variantText = $variantName && strtolower($variantName) !== 'default'
                ? " ({$variantName})"
                : '';

            return "  • {$productName}{$variantText} x{$item->quantity}";
        })->implode("\n");

        $addressText = $this->formatAddress($order);
        $trackUrl = route('storefront.orders.track');

        $lines = [
            "Halo {$recipientName},",
            '',
            $intro,
            '',
            'Detail Pesanan:',
            "- No. Pesanan: {$order->id}",
            "- Metode Bayar: {$paymentMethodLabel}",
        ];

        if ($itemLines !== '') {
            $lines[] = '- Item:';
            $lines[] = $itemLines;
        }

        $lines[] = "- Total: Rp {$formattedTotal}";

        if ($addressText !== null) {
            $lines[] = "- Alamat: {$addressText}";
        }

        foreach ($extraLines as $extraLine) {
            $lines[] = $extraLine;
        }

        $lines[] = '';
        $lines[] = 'Anda dapat melacak status pesanan melalui:';
        $lines[] = $trackUrl;
        $lines[] = '';
        $lines[] = 'Terima kasih,';
        $lines[] = 'AntaMart';

        $message = implode("\n", $lines);

        return [
            'phone'   => $phone,
            'label'   => $label,
            'message' => $message,
            'url'     => 'https://wa.me/' . $phone . '?text=' . rawurlencode($message),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, string>}
     */
    private function resolveStatusContent(Order $order, bool $isCod, bool $isPaid, string $formattedTotal): array
    {
        $extraLines = [];

        if ($order->status === OrderStatus::PendingPayment) {
            if ($order->payment?->xendit_payment_url) {
                $extraLines[] = "- Link Bayar QRIS: {$order->payment->xendit_payment_url}";
            }
            if ($order->expires_at) {
                $deadline = $order->expires_at->timezone(config('app.timezone'))->format('d M Y, H:i');
                $extraLines[] = "- Batas Pembayaran: {$deadline}";
            }

            return [
                'Kirim Link Bayar QRIS (WhatsApp)',
                "Kabar baik! Pesanan Anda di AntaMart telah kami konfirmasi tersedia.\nSilakan selesaikan pembayaran QRIS melalui tautan di bawah ini sebelum batas waktu berakhir.",
                $extraLines,
            ];
        }

        if ($order->status === OrderStatus::Processing) {
            if ($isCod && !$isPaid) {
                return [
                    'Kirim Konfirmasi COD (WhatsApp)',
                    "Terima kasih telah berbelanja di AntaMart!\nPesanan COD Anda sedang kami proses. Mohon siapkan uang tunai sebesar Rp {$formattedTotal} saat pesanan diterima.",
                    $extraLines,
                ];
            }

            return [
                'Kirim Konfirmasi Lunas (WhatsApp)',
                "Terima kasih telah berbelanja di AntaMart!\nPembayaran Anda telah kami terima dan pesanan sedang diproses.",
                $extraLines,
            ];
        }

        if ($order->status === OrderStatus::Shipped) {
            if ($order->shipment?->tracking_number) {
                $extraLines[] = "- No. Resi: {$order->shipment->tracking_number}";
            }
            if ($order->shipment?->tracking_url) {
                $extraLines[] = "- Link Tracking: {$order->shipment->tracking_url}";
            }

            $intro = $isCod && !$isPaid
                ? "Kabar baik! Pesanan COD Anda di AntaMart telah dikirim.\nMohon siapkan uang tunai sebesar Rp {$formattedTotal} saat kurir tiba."
                : 'Kabar baik! Pesanan Anda di AntaMart telah dikirim dan sedang dalam perjalanan.';

            return [
                'Kirim Info Pengiriman (WhatsApp)',
                $intro,
                $extraLines,
            ];
        }

        if ($order->status === OrderStatus::Completed) {
            return [
                'Kirim Info Pesanan Selesai (WhatsApp)',
                'Pesanan Anda di AntaMart telah selesai diterima. Terima kasih sudah berbelanja bersama kami!',
                $extraLines,
            ];
        }

        // Cancelled or Expired with Refund / Late Payment
        $refund = $order->refund;
        if ($refund && $refund->status === RefundStatus::Refunded) {
            $refundAmount = $this->formatRupiah((float) $refund->amount);
            $extraLines[] = "- Status Refund: Selesai (Rp {$refundAmount})";

            return [
                'Kirim Info Refund Selesai (WhatsApp)',
                "Informasi dari AntaMart: Pengembalian dana sebesar Rp {$refundAmount} untuk pesanan Anda telah kami proses.",
                $extraLines,
            ];
        }

        if ($refund && $refund->status === RefundStatus::ManualRefundPending) {
            $refundAmount = $this->formatRupiah((float) $refund->amount);
            $extraLines[] = "- Status Refund: Sedang diproses manual (Rp {$refundAmount})";

            return [
                'Kirim Info Pembatalan & Refund (WhatsApp)',
                "Informasi dari AntaMart: Pesanan Anda telah dibatalkan/kedaluwarsa dan pengembalian dana sebesar Rp {$refundAmount} sedang dalam proses manual oleh tim kami.",
                $extraLines,
            ];
        }

        if (!empty($order->rejection_reason)) {
            $extraLines[] = "- Alasan Penolakan: {$order->rejection_reason}";

            return [
                'Kirim Info Penolakan (WhatsApp)',
                'Mohon maaf, pesanan Anda di AntaMart tidak dapat kami proses saat ini.',
                $extraLines,
            ];
        }

        return [
            'Kirim Info Pembatalan (WhatsApp)',
            'Informasi dari AntaMart: Pesanan Anda telah dibatalkan.',
            $extraLines,
        ];
    }

    /**
     * Build a WhatsApp link for the customer to contact the store owner about order confirmation.
     *
     * @return array{phone: string, label: string, message: string, url: string}|null
     */
    public function buildCustomerToOwnerNotification(Order $order): ?array
    {
        $storePhone = $this->normalizePhone((string) config('services.store.whatsapp_number', ''));
        if (!$storePhone) {
            return null;
        }

        $order->loadMissing(['payment', 'items']);

        $isCod = $order->payment?->payment_method === PaymentMethod::CashOnDelivery;
        $methodLabel = $isCod ? 'COD (Bayar di Tempat)' : 'QRIS';
        $formattedTotal = $this->formatRupiah((float) $order->total);

        $lines = [
            'Halo AntaMart,',
            'Saya ingin menanyakan konfirmasi pesanan saya:',
            "- No. Pesanan: {$order->id}",
            "- Metode Bayar: {$methodLabel}",
            "- Total: Rp {$formattedTotal}",
            '',
            'Mohon informasi ketersediaan stoknya ya. Terima kasih!',
        ];

        $message = implode("\n", $lines);

        return [
            'phone'   => $storePhone,
            'label'   => 'Tanya Konfirmasi via WhatsApp',
            'message' => $message,
            'url'     => 'https://wa.me/' . $storePhone . '?text=' . rawurlencode($message),
        ];
    }

    private function formatPaymentMethod(bool $isCod, bool $isPaid): string
    {
        if ($isCod) {
            return $isPaid ? 'COD (Lunas)' : 'COD (Bayar di Tempat)';
        }

        return $isPaid ? 'QRIS (Lunas)' : 'QRIS';
    }

    private function formatRupiah(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    private function formatAddress(Order $order): ?string
    {
        $address = $order->address;
        if (!$address) {
            return null;
        }

        $parts = array_filter([
            $address->address_line1,
            $address->address_line2,
            $address->village ? "Desa/Kel. {$address->village}" : null,
            $address->district ? "Kec. {$address->district}" : null,
            $address->city,
            $address->province,
            $address->postal_code,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }
}

