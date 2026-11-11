<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\AuditEvent;
use App\Events\PaymentExpired;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BrowserCheckoutController extends Controller
{
    public function create(Request $request)
    {
        $customer = $request->user('web');
        abort_if($customer && !$customer->isCustomer(), 403);

        $pendingOrder = $this->pendingOrder();
        if ($pendingOrder) {
            return $this->paymentUnavailable($pendingOrder);
        }

        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('storefront.cart.index')->with('status', 'Keranjang masih kosong.');
        }

        $variants = ProductVariant::query()
            ->whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with('product')
            ->get()
            ->keyBy('id');

        $items = collect($cart)->map(function (int $quantity, int|string $id) use ($variants): ?array {
            $variant = $variants->get($id);
            if (!$variant || $quantity > $variant->available_stock) {
                return null;
            }

            return [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'image_url' => $variant->product->image_path ? Storage::disk('public')->url($variant->product->image_path) : null,
                'quantity' => $quantity,
                'price' => (float) $variant->price,
                'line_total' => (float) $variant->price * $quantity,
            ];
        })->values();

        if ($items->count() !== count($cart)) {
            return redirect()->route('storefront.cart.index')->withErrors([
                'cart' => 'Ada produk yang sudah tidak tersedia atau stoknya berubah. Perbarui keranjang sebelum checkout.',
            ]);
        }

        $lastCheckout = null;
        if ($customer) {
            $lastOrder = Order::query()
                ->where('customer_id', $customer->id)
                ->whereHas('address')
                ->latest()
                ->with('address')
                ->first();

            if ($lastOrder?->address) {
                $lastCheckout = [
                    'recipient_name' => $lastOrder->address->recipient_name,
                    'phone' => $lastOrder->address->phone,
                    'address_line1' => $lastOrder->address->address_line1,
                    'address_line2' => $lastOrder->address->address_line2 ?? '',
                    'city' => $lastOrder->address->city,
                    'province' => $lastOrder->address->province,
                    'postal_code' => $lastOrder->address->postal_code,
                ];
            }
        } elseif (session()->has('last_checkout_data')) {
            $lastCheckout = session('last_checkout_data');
        } else {
            $recentOrderId = session('last_completed_order_id');
            if ($recentOrderId) {
                $lastOrder = Order::query()->whereKey($recentOrderId)->with('address')->first();
                if ($lastOrder?->address) {
                    $lastCheckout = [
                        'guest_email' => $lastOrder->guest_email,
                        'guest_phone' => $lastOrder->guest_phone,
                        'recipient_name' => $lastOrder->address->recipient_name,
                        'phone' => $lastOrder->address->phone,
                        'address_line1' => $lastOrder->address->address_line1,
                        'address_line2' => $lastOrder->address->address_line2 ?? '',
                        'city' => $lastOrder->address->city,
                        'province' => $lastOrder->address->province,
                        'postal_code' => $lastOrder->address->postal_code,
                    ];
                }
            }
        }

        return view('storefront.checkout.create', [
            'items' => $items,
            'subtotal' => $items->sum('line_total'),
            'customer' => $customer,
            'lastCheckout' => $lastCheckout,
        ]);
    }

    public function store(Request $request, CheckoutService $checkoutService, PaymentService $paymentService)
    {
        if ($pendingOrder = $this->pendingOrder()) {
            return $this->continuePayment($pendingOrder, $paymentService);
        }

        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('storefront.cart.index')->withErrors(['cart' => 'Keranjang masih kosong.']);
        }

        $customer = $request->user('web');
        abort_if($customer && !$customer->isCustomer(), 403);
        $isGuest = !$customer;

        $validated = Validator::make($request->all(), [
            'guest_email' => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
            'guest_phone' => [$isGuest ? 'required' : 'nullable', 'string', 'max:30'],
            'shipping_address.recipient_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.address_line1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.province' => ['required', 'string', 'max:100'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
            'payment_method' => ['required', 'in:xendit,cod'],
        ])->validate();

        $items = [];
        foreach ($cart as $variantId => $quantity) {
            $items[] = [
                'product_variant_id' => (int) $variantId,
                'quantity' => (int) $quantity,
            ];
        }

        $idempotencyKey = session('checkout.idempotency_key') ?? (string) Str::uuid();
        session([
            'checkout.idempotency_key' => $idempotencyKey,
            'last_checkout_data' => [
                'guest_email' => $validated['guest_email'] ?? null,
                'guest_phone' => $validated['guest_phone'] ?? null,
                'recipient_name' => $validated['shipping_address']['recipient_name'],
                'phone' => $validated['shipping_address']['phone'],
                'address_line1' => $validated['shipping_address']['address_line1'],
                'address_line2' => $validated['shipping_address']['address_line2'] ?? '',
                'city' => $validated['shipping_address']['city'],
                'province' => $validated['shipping_address']['province'],
                'postal_code' => $validated['shipping_address']['postal_code'],
            ],
        ]);

        try {
            $order = $checkoutService->checkout([
                ...$validated,
                'idempotency_key' => $idempotencyKey,
                'payment_method' => $validated['payment_method'],
                'items' => $items,
                'shipping_address' => [
                    ...$validated['shipping_address'],
                    'country' => 'ID',
                ],
            ], $customer);
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'cart' => 'Stok berubah sebelum pesanan dibuat. Perbarui keranjang lalu coba lagi.',
            ]);
        }

        return $this->continuePayment($order, $paymentService);
    }

    public function retryPayment(PaymentService $paymentService)
    {
        $order = $this->pendingOrder();
        if (!$order) {
            return redirect()->route('storefront.cart.index')->with('status', 'Tidak ada pesanan aktif yang menunggu pembayaran.');
        }

        return $this->continuePayment($order, $paymentService);
    }

    public function switchToCod(InventoryService $inventoryService)
    {
        $order = $this->pendingOrder();
        if (!$order) {
            return redirect()->route('storefront.cart.index')->withErrors(['cart' => 'Tidak ada pesanan aktif.']);
        }

        DB::transaction(function () use ($order, $inventoryService) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                return;
            }

            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            if ($payment) {
                $payment->update([
                    'payment_method' => PaymentMethod::CashOnDelivery,
                    'status' => PaymentStatus::Pending,
                ]);
            }

            $lockedOrder->transitionTo(OrderStatus::Processing);
            $inventoryService->convertReservationsToPhysicalDeduction($lockedOrder);

            AuditEvent::dispatch(
                'payment.cod_order_created',
                $lockedOrder->id,
                $lockedOrder->customer_id,
                $lockedOrder->customer_id ? 'customer' : 'guest',
                ['amount' => $lockedOrder->total, 'switched_from' => 'xendit']
            );
        });

        session()->forget(['cart', 'checkout.order_id', 'checkout.idempotency_key']);

        return redirect()->to(URL::temporarySignedRoute(
            'storefront.checkout.result',
            now()->addDays(3),
            ['orderId' => $order->id]
        ))->with('status', 'Metode pembayaran berhasil dialihkan ke Bayar di Tempat (COD). Pesanan sedang kami proses!');
    }

    public function cancelPending(InventoryService $inventoryService)
    {
        $order = $this->pendingOrder();
        if (!$order) {
            session()->forget(['checkout.order_id', 'checkout.idempotency_key']);
            return redirect()->route('storefront.cart.index');
        }

        DB::transaction(function () use ($order, $inventoryService) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status === OrderStatus::PendingPayment) {
                $lockedOrder->transitionTo(OrderStatus::Expired);

                $payment = $lockedOrder->payment()->lockForUpdate()->first();
                if ($payment && $payment->status === PaymentStatus::Pending) {
                    $payment->status = PaymentStatus::Expired;
                    $payment->save();
                }

                $inventoryService->releaseReservations($lockedOrder);
                PaymentExpired::dispatch($lockedOrder);

                AuditEvent::dispatch(
                    'order.cancelled_by_customer',
                    $lockedOrder->id,
                    $lockedOrder->customer_id,
                    $lockedOrder->customer_id ? 'customer' : 'guest',
                    ['reason' => 'cancelled_from_payment_unavailable']
                );
            }
        });

        // Restore items back to customer session cart without duplicating
        $cart = session('cart', []);
        foreach ($order->items as $item) {
            $cart[$item->product_variant_id] = max((int) ($cart[$item->product_variant_id] ?? 0), $item->quantity);
        }
        session(['cart' => $cart]);

        session()->forget(['checkout.order_id', 'checkout.idempotency_key']);

        return redirect()->route('storefront.cart.index')->with('status', 'Pesanan berhasil dibatalkan dan produk dikembalikan ke keranjang Anda.');
    }

    public function devSimulatePayment(InventoryService $inventoryService)
    {
        abort_unless(app()->environment('local'), 403);

        $order = $this->pendingOrder();
        if (!$order) {
            return redirect()->route('storefront.cart.index');
        }

        DB::transaction(function () use ($order, $inventoryService) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                return;
            }

            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            if ($payment) {
                $payment->update([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'xendit_invoice_id' => $payment->xendit_invoice_id ?? ('dev_sim_' . Str::random(12)),
                    'xendit_payment_url' => $payment->xendit_payment_url ?? 'https://checkout.xendit.test/dev-simulated',
                ]);
            }

            $lockedOrder->transitionTo(OrderStatus::Processing);
            $inventoryService->convertReservationsToPhysicalDeduction($lockedOrder);

            AuditEvent::dispatch(
                'payment.confirmed',
                $lockedOrder->id,
                $lockedOrder->customer_id,
                'system',
                ['amount' => $lockedOrder->total, 'mode' => 'dev_simulation']
            );
        });

        session()->forget(['cart', 'checkout.order_id', 'checkout.idempotency_key']);

        return redirect()->to(URL::temporarySignedRoute(
            'storefront.checkout.result',
            now()->addDays(3),
            ['orderId' => $order->id]
        ))->with('status', '[Mode Lokal] Simulasi pembayaran Xendit berhasil. Pesanan langsung diproses!');
    }

    public function result(string $orderId)
    {
        $order = Order::query()
            ->whereKey($orderId)
            ->with(['items', 'payment', 'shipment', 'refund', 'latestCancellationRequest'])
            ->firstOrFail();

        return view('storefront.checkout.result', [
            'order' => $order,
            'retryUrl' => URL::temporarySignedRoute(
                'storefront.checkout.result.retry',
                now()->addDays(3),
                ['orderId' => $order->id]
            ),
        ]);
    }

    public function retryResultPayment(string $orderId, PaymentService $paymentService)
    {
        $order = Order::query()->whereKey($orderId)->with('payment')->firstOrFail();

        if ($order->status->value !== 'pending_payment') {
            return redirect()->back()->with('status', 'Status pesanan sudah berubah. Periksa informasi terbaru di halaman ini.');
        }

        if ($order->payment?->xendit_payment_url && $order->expires_at?->isFuture()) {
            return redirect()->away($order->payment->xendit_payment_url);
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            return redirect()->back()->withErrors([
                'payment' => 'Batas waktu pembayaran telah lewat. Status pesanan sedang diperbarui; muat ulang halaman ini sebentar lagi.',
            ]);
        }

        $paymentSession = $paymentService->createPaymentSession($order);
        if (!$paymentSession) {
            return redirect()->back()->withErrors([
                'payment' => 'Tautan pembayaran belum tersedia. Coba lagi sebentar.',
            ]);
        }

        return redirect()->away($paymentSession['payment_url']);
    }

    private function pendingOrder(): ?Order
    {
        $orderId = session('checkout.order_id');
        $key = session('checkout.idempotency_key');
        if (!$orderId || !$key) {
            return null;
        }

        $order = Order::query()
            ->whereKey($orderId)
            ->where('idempotency_key', $key)
            ->with(['items', 'address', 'payment'])
            ->first();

        if (!$order || $order->status !== OrderStatus::PendingPayment || ($order->expires_at && $order->expires_at->isPast())) {
            session()->forget(['checkout.order_id', 'checkout.idempotency_key']);

            return null;
        }

        return $order;
    }

    private function continuePayment(Order $order, PaymentService $paymentService)
    {
        $order->loadMissing(['address', 'payment']);
        if ($order->status !== OrderStatus::PendingPayment) {
            session()->forget(['cart', 'checkout.order_id', 'checkout.idempotency_key']);

            return redirect()->to(URL::temporarySignedRoute(
                'storefront.checkout.result',
                now()->addDays(3),
                ['orderId' => $order->id]
            ));
        }

        $paymentUrl = $order->payment?->xendit_payment_url;

        if (!$paymentUrl) {
            $invoice = $paymentService->createPaymentSession($order);
            $paymentUrl = $invoice['payment_url'] ?? null;
        }

        if (!$paymentUrl) {
            session([
                'checkout.order_id' => $order->id,
                'checkout.idempotency_key' => $order->idempotency_key,
            ]);

            return $this->paymentUnavailable(
                $order->fresh(['items', 'payment']),
                'Tautan pembayaran Xendit belum berhasil dibuat (gateway belum terhubung atau API key belum dikonfigurasi). Silakan coba lagi, beralih ke Bayar di Tempat (COD), atau batalkan pesanan untuk memulai ulang.'
            );
        }

        session()->forget(['cart', 'checkout.order_id', 'checkout.idempotency_key']);

        return redirect()->away($paymentUrl);
    }

    private function paymentUnavailable(Order $order, ?string $errorMessage = null)
    {
        return response()->view('storefront.checkout.payment-unavailable', [
            'order' => $order->loadMissing(['items', 'payment']),
            'errorMessage' => $errorMessage,
        ]);
    }
}
