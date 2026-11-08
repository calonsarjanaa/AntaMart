<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->with(['customer', 'items', 'address', 'payment', 'shipment', 'refund', 'latestCancellationRequest'])
            ->whereKey($orderId)
            ->firstOrFail();

        $auditLogs = $order->auditLogs()
            ->with('actor')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        return view('owner.orders.show', compact('order', 'auditLogs'));
    }
}
