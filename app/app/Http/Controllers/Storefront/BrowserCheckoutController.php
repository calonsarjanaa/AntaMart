<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BrowserCheckoutController extends Controller
{
    public function create()
    {
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

        return view('storefront.checkout.create', [
            'items' => $items,
            'subtotal' => $items->sum('line_total'),
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

        $validated = Validator::make($request->all(), [
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'shipping_address.recipient_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.address_line1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.province' => ['required', 'string', 'max:100'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
        ])->validate();

        $items = [];
        foreach ($cart as $variantId => $quantity) {
            $items[] = [
                'product_variant_id' => (int) $variantId,
                'quantity' => (int) $quantity,
            ];
        }

        $idempotencyKey = session('checkout.idempotency_key') ?? (string) Str::uuid();
        session(['checkout.idempotency_key' => $idempotencyKey]);

        try {
            $order = $checkoutService->checkout([
                ...$validated,
                'idempotency_key' => $idempotencyKey,
                'items' => $items,
                'shipping_address' => [
                    ...$validated['shipping_address'],
                    'country' => 'ID',
                ],
            ], $request->user());
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
        abort_unless($order, 404);

        return $this->continuePayment($order, $paymentService);
    }

    private function pendingOrder(): ?Order
    {
        $orderId = session('checkout.order_id');
        $key = session('checkout.idempotency_key');
        if (!$orderId || !$key) {
            return null;
        }

        return Order::query()
            ->whereKey($orderId)
            ->where('idempotency_key', $key)
            ->with(['items', 'address', 'payment'])
            ->first();
    }

    private function continuePayment(Order $order, PaymentService $paymentService)
    {
        $order->loadMissing(['address', 'payment']);
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

            return $this->paymentUnavailable($order->fresh(['items', 'payment']));
        }

        session()->forget(['cart', 'checkout.order_id', 'checkout.idempotency_key']);

        return redirect()->away($paymentUrl);
    }

    private function paymentUnavailable(Order $order)
    {
        return response()->view('storefront.checkout.payment-unavailable', [
            'order' => $order->loadMissing(['items', 'payment']),
        ]);
    }
}
