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
use App\Services\AgeVerificationService;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderExpiryService;
use App\Services\PaymentService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BrowserCheckoutController extends Controller
{
    public function create(Request $request, AgeVerificationService $ageVerificationService)
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
            ->with('product.category')
            ->get()
            ->keyBy('id');

        foreach ($variants as $variant) {
            if ($variant->product->isAgeRestricted()) {
                $category = $variant->product->category;
                $minAge = $category->min_age;
                $ageStatus = $ageVerificationService->status($customer, $minAge);

                if ($ageStatus === 'underage') {
                    return redirect()->route('storefront.cart.index')->withErrors([
                        'birth_date' => "Maaf, usia pada profil akun Anda belum memenuhi syarat minimal {$minAge} tahun untuk membeli produk kategori {$category->name}.",
                    ]);
                }

                if ($ageStatus !== 'verified') {
                    return redirect()->route('storefront.age-verification.show', [
                        'category' => $category->slug,
                        'return_url' => route('storefront.checkout.create'),
                    ]);
                }
            }
        }

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
                    'province' => $lastOrder->address->province,
                    'city' => $lastOrder->address->city,
                    'district' => $lastOrder->address->district ?? '',
                    'village' => $lastOrder->address->village ?? '',
                    'address_line1' => $lastOrder->address->address_line1,
                    'address_line2' => $lastOrder->address->address_line2 ?? '',
                    'postal_code' => $lastOrder->address->postal_code ?? '',
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
                        'province' => $lastOrder->address->province,
                        'city' => $lastOrder->address->city,
                        'district' => $lastOrder->address->district ?? '',
                        'village' => $lastOrder->address->village ?? '',
                        'address_line1' => $lastOrder->address->address_line1,
                        'address_line2' => $lastOrder->address->address_line2 ?? '',
                        'postal_code' => $lastOrder->address->postal_code ?? '',
                    ];
                }
            }
        }

        return view('storefront.checkout.create', [
            'items' => $items,
            'subtotal' => $items->sum('line_total'),
            'customer' => $customer,
            'profileAddress' => $customer?->profileAddress(),
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
            'shipping_address.province' => ['required', 'string', 'max:100'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.district' => ['nullable', 'string', 'max:100'],
            'shipping_address.village' => ['nullable', 'string', 'max:150'],
            'shipping_address.address_line1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
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
                'province' => $validated['shipping_address']['province'],
                'city' => $validated['shipping_address']['city'],
                'district' => $validated['shipping_address']['district'] ?? '',
                'village' => $validated['shipping_address']['village'] ?? '',
                'address_line1' => $validated['shipping_address']['address_line1'],
                'address_line2' => $validated['shipping_address']['address_line2'] ?? '',
                'postal_code' => $validated['shipping_address']['postal_code'] ?? '',
            ],
        ]);

        try {
            $order = $checkoutService->checkout([
                ...$validated,
                'idempotency_key' => $idempotencyKey,
                'payment_method' => $validated['payment_method'],
                'birth_date' => $customer?->birth_date?->format('Y-m-d') ?? session('age_verified_birth_date'),
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

    public function result(
        string $orderId,
        PaymentService $paymentService,
        OrderExpiryService $orderExpiryService,
        WhatsAppNotificationService $whatsAppService
    ) {
        $order = Order::query()
            ->whereKey($orderId)
            ->with(['items', 'payment', 'shipment', 'refund', 'latestCancellationRequest'])
            ->firstOrFail();

        if ($order->status === OrderStatus::PendingPayment && $order->payment?->xendit_invoice_id) {
            if ($paymentService->syncPaymentStatus($order)) {
                $order->refresh()->load(['items', 'payment', 'shipment', 'refund', 'latestCancellationRequest']);
            }
        }

        if ($order->status === OrderStatus::PendingPayment && $order->expires_at?->isPast()) {
            if ($orderExpiryService->expireOrder($order)) {
                $order->refresh()->load(['items', 'payment', 'shipment', 'refund', 'latestCancellationRequest']);
            }
        }

        return view('storefront.checkout.result', [
            'order' => $order,
            'storeWhatsApp' => $whatsAppService->buildCustomerToOwnerNotification($order),
            'payUrl' => URL::temporarySignedRoute(
                'storefront.checkout.result.pay',
                now()->addDays(3),
                ['orderId' => $order->id]
            ),
            'retryUrl' => URL::temporarySignedRoute(
                'storefront.checkout.result.retry',
                now()->addDays(3),
                ['orderId' => $order->id]
            ),
            'switchCodUrl' => URL::temporarySignedRoute(
                'storefront.checkout.result.switch-cod',
                now()->addDays(3),
                ['orderId' => $order->id]
            ),
            'cancelUrl' => URL::temporarySignedRoute(
                'storefront.checkout.result.cancel',
                now()->addDays(3),
                ['orderId' => $order->id]
            ),
        ]);
    }

    /**
     * Guarded payment redirect: locks payment access if order was cancelled/expired after page loaded.
     */
    public function payResult(
        string $orderId,
        PaymentService $paymentService,
        OrderExpiryService $orderExpiryService
    ) {
        $order = Order::query()->whereKey($orderId)->with('payment')->firstOrFail();

        $resultUrl = URL::temporarySignedRoute(
            'storefront.checkout.result',
            now()->addDays(3),
            ['orderId' => $order->id]
        );

        if ($order->status === OrderStatus::PendingPayment && $order->payment?->xendit_invoice_id) {
            if ($paymentService->syncPaymentStatus($order)) {
                return redirect()->to($resultUrl)
                    ->with('status', 'Pembayaran pesanan ini sudah dikonfirmasi.');
            }
        }

        if ($order->status === OrderStatus::Cancelled) {
            $reason = $order->rejection_reason ?: $order->latestCancellationRequest?->reason;
            $msg = 'Pesanan ini telah dibatalkan sehingga pembayaran tidak dapat dilanjutkan.'
                . ($reason ? " Alasan: {$reason}" : '');

            return redirect()->to($resultUrl)->withErrors(['payment' => $msg]);
        }

        if ($order->status === OrderStatus::Expired || ($order->status === OrderStatus::PendingPayment && $order->expires_at?->isPast())) {
            $orderExpiryService->expireOrder($order);

            return redirect()->to($resultUrl)->withErrors([
                'payment' => 'Batas waktu pembayaran telah lewat. Pesanan telah kedaluwarsa.',
            ]);
        }

        if ($order->status !== OrderStatus::PendingPayment || $order->payment?->status !== PaymentStatus::Pending) {
            return redirect()->to($resultUrl)
                ->with('status', 'Status pesanan sudah berubah. Periksa informasi terbaru di halaman ini.');
        }

        if (empty($order->payment?->xendit_payment_url)) {
            return redirect()->to($resultUrl)->withErrors([
                'payment' => 'Tautan pembayaran belum tersedia. Silakan muat ulang atau coba buat tautan lagi.',
            ]);
        }

        return redirect()->away($order->payment->xendit_payment_url);
    }

    public function switchResultToCod(
        string $orderId,
        PaymentService $paymentService,
        InventoryService $inventoryService,
        OrderExpiryService $orderExpiryService
    ) {
        $order = Order::query()->whereKey($orderId)->with(['items', 'payment'])->firstOrFail();

        if ($order->status === OrderStatus::PendingPayment && $order->payment?->xendit_invoice_id) {
            if ($paymentService->syncPaymentStatus($order)) {
                return redirect()->to(URL::temporarySignedRoute(
                    'storefront.checkout.result',
                    now()->addDays(3),
                    ['orderId' => $order->id]
                ))->with('status', 'Pembayaran Xendit sudah dikonfirmasi. Pesanan sedang diproses!');
            }
        }

        if ($order->status === OrderStatus::PendingPayment && $order->expires_at?->isPast()) {
            $orderExpiryService->expireOrder($order);

            return redirect()->back()->withErrors([
                'payment' => 'Batas waktu pembayaran telah lewat sehingga tidak dapat dialihkan ke COD.',
            ]);
        }

        if ($order->status !== OrderStatus::PendingPayment) {
            return redirect()->back()->with('status', 'Status pesanan sudah berubah dan tidak dapat dialihkan ke COD.');
        }

        DB::transaction(function () use ($order, $inventoryService): void {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                return;
            }

            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            if ($payment) {
                $payment->update([
                    'payment_method' => PaymentMethod::CashOnDelivery,
                    'status' => PaymentStatus::Pending,
                    'expired_at' => null,
                ]);
            }

            $lockedOrder->update(['expires_at' => null]);
            $lockedOrder->transitionTo(OrderStatus::Processing);
            $inventoryService->convertReservationsToPhysicalDeduction($lockedOrder);

            AuditEvent::dispatch(
                'payment.cod_order_created',
                $lockedOrder->id,
                $lockedOrder->customer_id,
                $lockedOrder->customer_id ? 'customer' : 'guest',
                ['amount' => $lockedOrder->total, 'switched_from' => 'xendit_result']
            );
        });

        return redirect()->to(URL::temporarySignedRoute(
            'storefront.checkout.result',
            now()->addDays(3),
            ['orderId' => $order->id]
        ))->with('status', 'Metode pembayaran berhasil dialihkan ke Bayar di Tempat (COD). Pesanan sedang kami proses!');
    }

    public function cancelResultPending(
        Request $request,
        string $orderId,
        PaymentService $paymentService,
        InventoryService $inventoryService
    ) {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $reason = trim((string) ($data['reason'] ?? '')) ?: 'Dibatalkan oleh pembeli.';

        $order = Order::query()->whereKey($orderId)->with(['items', 'payment'])->firstOrFail();

        if ($order->status === OrderStatus::PendingPayment && $order->payment?->xendit_invoice_id) {
            if ($paymentService->syncPaymentStatus($order)) {
                return redirect()->to(URL::temporarySignedRoute(
                    'storefront.checkout.result',
                    now()->addDays(3),
                    ['orderId' => $order->id]
                ))->with('status', 'Pembayaran Xendit sudah dikonfirmasi sehingga pesanan tidak dibatalkan.');
            }
        }

        if (!in_array($order->status, [OrderStatus::AwaitingConfirmation, OrderStatus::PendingPayment], true)) {
            return redirect()->back()->with('status', 'Status pesanan sudah berubah dan tidak dapat dibatalkan dari halaman ini.');
        }

        $invoiceToExpire = null;

        DB::transaction(function () use ($order, $inventoryService, $reason, &$invoiceToExpire): void {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!in_array($lockedOrder->status, [OrderStatus::AwaitingConfirmation, OrderStatus::PendingPayment], true)) {
                return;
            }

            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            $invoiceToExpire = $payment?->xendit_invoice_id;

            $lockedOrder->transitionTo(OrderStatus::Cancelled);

            if ($payment && $payment->status === PaymentStatus::Pending) {
                $payment->status = PaymentStatus::Expired;
                $payment->save();
            }

            $inventoryService->releaseReservations($lockedOrder);

            $lockedOrder->cancellationRequests()->create([
                'requested_by' => $lockedOrder->customer_id,
                'reason'       => $reason,
                'status'       => \App\Enums\CancellationStatus::Approved,
                'decided_at'   => now(),
            ]);

            AuditEvent::dispatch(
                'order.cancelled_by_customer',
                $lockedOrder->id,
                $lockedOrder->customer_id,
                $lockedOrder->customer_id ? 'customer' : 'guest',
                ['reason' => $reason, 'source' => 'result_page']
            );
        });

        $gateway = app(\App\Adapters\Payment\PaymentGatewayInterface::class);
        if ($invoiceToExpire && method_exists($gateway, 'expireInvoice')) {
            $gateway->expireInvoice($invoiceToExpire);
        }

        $cart = session('cart', []);
        foreach ($order->items as $item) {
            $cart[$item->product_variant_id] = max((int) ($cart[$item->product_variant_id] ?? 0), $item->quantity);
        }
        session(['cart' => $cart]);

        return redirect()->route('storefront.cart.index')
            ->with('status', 'Pesanan berhasil dibatalkan, stok dilepas, dan produk dikembalikan ke keranjang Anda.');
    }

    public function retryResultPayment(string $orderId, PaymentService $paymentService, OrderExpiryService $orderExpiryService)
    {
        $order = Order::query()->whereKey($orderId)->with('payment')->firstOrFail();

        if ($order->status->value !== 'pending_payment') {
            return redirect()->back()->with('status', 'Status pesanan sudah berubah. Periksa informasi terbaru di halaman ini.');
        }

        if ($order->payment?->xendit_payment_url && $order->expires_at?->isFuture()) {
            return redirect()->away($order->payment->xendit_payment_url);
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            $orderExpiryService->expireOrder($order);

            return redirect()->back()->withErrors([
                'payment' => 'Batas waktu pembayaran telah lewat. Pesanan telah ditandai kedaluwarsa dan stok dilepaskan.',
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
        if ($order->status === OrderStatus::AwaitingConfirmation) {
            session()->forget(['cart', 'checkout.order_id', 'checkout.idempotency_key']);

            return redirect()->to(URL::temporarySignedRoute(
                'storefront.checkout.result',
                now()->addDays(3),
                ['orderId' => $order->id]
            ))->with('status', 'Pesanan berhasil dibuat! Menunggu konfirmasi ketersediaan stok dari toko.');
        }

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

        return redirect()->to(URL::temporarySignedRoute(
            'storefront.checkout.result',
            now()->addDays(3),
            ['orderId' => $order->id]
        ))->with('status', 'Pesanan berhasil dibuat! Silakan klik "Bayar Pesanan" untuk menyelesaikan pembayaran.');
    }

    private function paymentUnavailable(Order $order, ?string $errorMessage = null)
    {
        return response()->view('storefront.checkout.payment-unavailable', [
            'order' => $order->loadMissing(['items', 'payment']),
            'errorMessage' => $errorMessage,
        ]);
    }
}
