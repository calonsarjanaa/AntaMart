<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class OwnerOrderController extends Controller
{
    public function index(Request $request)
    {
        $orderStatuses = collect(OrderStatus::cases())->map(fn (OrderStatus $status) => $status->value)->all();
        $paymentStatuses = collect(PaymentStatus::cases())->map(fn (PaymentStatus $status) => $status->value)->all();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in($orderStatuses)],
            'payment_status' => ['nullable', Rule::in($paymentStatuses)],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $filters['q'] = $search !== '' ? $search : null;

        $query = Order::query()
            ->with(['customer', 'payment', 'shipment'])
            ->withCount('items')
            ->latest();

        if ($search !== '') {
            $query->where(function ($orders) use ($search): void {
                $orders->where('id', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('email', 'like', "%{$search}%"));
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->whereHas('payment', fn ($payment) => $payment->where('status', $filters['payment_status']));
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('owner.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'hasFilters' => $search !== '' || !empty($filters['status']) || !empty($filters['payment_status']),
            'orderStatuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
        ]);
    }

    public function show(string $orderId)
    {
        $order = Order::query()
            ->with(['customer', 'items', 'address', 'payment', 'shipment', 'refund.refundedBy', 'latestCancellationRequest'])
            ->whereKey($orderId)
            ->firstOrFail();

        $convertedQuantities = $order->reservations()
            ->where('status', 'converted')
            ->get(['product_variant_id', 'quantity'])
            ->groupBy('product_variant_id')
            ->map(fn ($reservations) => $reservations->sum('quantity'));
        $orderItemQuantities = $order->items
            ->groupBy('product_variant_id')
            ->map(fn ($items) => $items->sum('quantity'));
        $eligibleResellableItemIds = $order->items
            ->filter(fn ($item) => (int) ($convertedQuantities->get($item->product_variant_id) ?? 0)
                >= (int) ($orderItemQuantities->get($item->product_variant_id) ?? 0))
            ->pluck('id')
            ->all();

        $auditLogs = $order->auditLogs()
            ->with('actor')
            ->latest('created_at')
            ->limit(21)
            ->get()
            ->values();
        $hasMoreAuditLogs = $auditLogs->count() > 20;
        $auditLogs = $auditLogs->take(20)->reverse()->values();

        $eventLabels = [
            'order.created' => 'Pesanan dibuat',
            'payment.session_created' => 'Invoice pembayaran dibuat',
            'payment.confirmed' => 'Pembayaran dikonfirmasi',
            'payment.late_detected' => 'Pembayaran terlambat diterima',
            'payment.expired' => 'Batas pembayaran berakhir',
            'stock.deducted' => 'Stok dikurangi untuk pesanan',
            'cancellation.requested' => 'Permintaan pembatalan diajukan',
            'cancellation.withdrawn' => 'Permintaan pembatalan ditarik',
            'cancellation.approved' => 'Pembatalan disetujui',
            'cancellation.rejected' => 'Pembatalan ditolak',
            'refund.flagged' => 'Pengembalian dana perlu diproses manual',
            'refund.marked_refunded' => 'Pengembalian dana dicatat selesai',
            'tracking.added' => 'Informasi pengiriman dicatat',
            'shipment.delivered' => 'Pesanan ditandai diterima',
        ];

        $auditLogs->each(function ($log) use ($eventLabels): void {
            $log->setAttribute('display_event', $eventLabels[$log->event] ?? Str::headline(str_replace('.', ' ', $log->event)));
            $log->setAttribute('display_actor', $log->actor?->name ?? match ($log->actor_type) {
                'system' => 'Sistem',
                'guest' => 'Tamu',
                'store_owner' => 'Pemilik toko',
                'customer' => 'Pelanggan',
                default => Str::headline($log->actor_type ?? 'system'),
            });
            $log->setAttribute('display_summary', $this->auditSummary($log->event, $log->payload ?? []));
        });

        return view('owner.orders.show', compact('order', 'auditLogs', 'eligibleResellableItemIds', 'hasMoreAuditLogs'));
    }

    /** @param array<string, mixed> $payload */
    private function auditSummary(string $event, array $payload): ?string
    {
        if (in_array($event, [
            'order.created', 'payment.session_created', 'payment.confirmed', 'payment.late_detected',
            'refund.flagged', 'refund.marked_refunded',
        ], true) && is_numeric($payload['amount'] ?? $payload['total'] ?? null)) {
            $amount = number_format((float) ($payload['amount'] ?? $payload['total']), 0, ',', '.');
            $summary = "Nominal Rp {$amount}";

            if ($event === 'order.created' && is_numeric($payload['item_count'] ?? null)) {
                $summary .= ' · ' . (int) $payload['item_count'] . ' item';
            }

            return $summary;
        }

        if ($event === 'stock.deducted' && is_array($payload['items'] ?? null)) {
            $quantities = array_filter(array_column($payload['items'], 'quantity'), 'is_numeric');
            $count = count($payload['items']);

            return $count ? "{$count} varian · " . array_sum($quantities) . ' unit' : null;
        }

        if ($event === 'cancellation.approved' && ($payload['owner_initiated'] ?? false) === true) {
            return 'Pembatalan dimulai oleh pemilik toko';
        }

        if ($event === 'tracking.added' && isset($payload['method'])) {
            return 'Metode: ' . Str::headline((string) $payload['method']);
        }

        return null;
    }
}
