<?php

namespace App\Services;

use App\Adapters\Shipping\ShippingProviderInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ShippingProviderInterface $shippingProvider
    ) {}

    /**
     * Process checkout and create an order with stock reservations.
     *
     * @param array<string, mixed> $data
     * @param User|null $user
     * @return Order
     * @throws InsufficientStockException|ValidationException
     */
    public function checkout(array $data, ?User $user = null): Order
    {
        $idempotencyKey = $data['idempotency_key'];

        // FR-TRX-005 — Idempotency check: return existing order if previously created
        $existingOrder = Order::where('idempotency_key', $idempotencyKey)
            ->with(['items', 'address', 'payment'])
            ->first();

        if ($existingOrder) {
            return $existingOrder;
        }

        // FR-TRX-001 — Guest verification
        $isGuest = is_null($user);
        $guestEmail = $isGuest ? ($data['guest_email'] ?? null) : null;
        $guestPhone = $isGuest ? ($data['guest_phone'] ?? null) : null;

        if ($isGuest && (empty($guestEmail) || empty($guestPhone))) {
            throw ValidationException::withMessages([
                'guest_email' => 'Guest checkout requires a valid email address.',
                'guest_phone' => 'Guest checkout requires a valid phone number.',
            ]);
        }

        // FR-TRX-002 — Validate cart items and fetch authoritative catalog data
        $itemsData = $data['items'] ?? [];
        if (empty($itemsData)) {
            throw ValidationException::withMessages([
                'items' => 'The order must contain at least one item.',
            ]);
        }

        $variantIds = array_column($itemsData, 'product_variant_id');
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $preparedItems = [];
        $subtotal = 0.00;

        foreach ($itemsData as $item) {
            $variantId = $item['product_variant_id'];
            $quantity = (int) $item['quantity'];

            if (!$variants->has($variantId)) {
                throw ValidationException::withMessages([
                    'items' => "Product variant [ID: {$variantId}] is inactive or does not exist.",
                ]);
            }

            /** @var ProductVariant $variant */
            $variant = $variants->get($variantId);
            $unitPrice = (float) $variant->price;
            $lineSubtotal = $unitPrice * $quantity;
            $subtotal += $lineSubtotal;

            $preparedItems[] = [
                'variant'       => $variant,
                'quantity'      => $quantity,
                'unit_price'    => $unitPrice,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        // FR-SHP-003 — Snapshot shipping cost
        $shippingCost = $this->shippingProvider->calculateCost(
            $data['shipping_address'],
            $preparedItems
        );

        $total = $subtotal + $shippingCost;

        // Atomic transaction: Order creation + Items snapshot + Address snapshot + Stock reservation + Payment record
        return DB::transaction(function () use (
            $user,
            $guestEmail,
            $guestPhone,
            $idempotencyKey,
            $subtotal,
            $shippingCost,
            $total,
            $preparedItems,
            $itemsData,
            $data
        ) {
            // FR-TRX-003 & FR-TRX-004 — Create Order with snapshot values
            $order = Order::create([
                'customer_id'     => $user?->id,
                'guest_email'     => $guestEmail,
                'guest_phone'     => $guestPhone,
                'status'          => OrderStatus::PendingPayment,
                'subtotal'        => $subtotal,
                'shipping_cost'   => $shippingCost,
                'total'           => $total,
                'idempotency_key' => $idempotencyKey,
            ]);

            // FR-TRX-004 — Snapshot product details into order_items
            foreach ($preparedItems as $prep) {
                /** @var ProductVariant $variant */
                $variant = $prep['variant'];

                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_snapshot'   => [
                        'product_id' => $variant->product_id,
                        'name'       => $variant->name,
                    ],
                    'variant_snapshot'   => [
                        'variant_id' => $variant->id,
                        'sku'        => $variant->sku,
                        'name'       => $variant->name,
                    ],
                    'sku'                => $variant->sku,
                    'quantity'           => $prep['quantity'],
                    'unit_price'         => $prep['unit_price'],
                    'subtotal'           => $prep['line_subtotal'],
                ]);
            }

            // FR-ADR-001 — Snapshot shipping address
            $addr = $data['shipping_address'];
            OrderAddress::create([
                'order_id'       => $order->id,
                'recipient_name' => $addr['recipient_name'],
                'phone'          => $addr['phone'],
                'address_line1'  => $addr['address_line1'],
                'address_line2'  => $addr['address_line2'] ?? null,
                'city'           => $addr['city'],
                'province'       => $addr['province'],
                'postal_code'    => $addr['postal_code'],
                'country'        => $addr['country'] ?? 'ID',
            ]);

            // FR-INV-001 & FR-INV-003 — Reserve stock atomically
            $this->inventoryService->reserveStock($order, $itemsData);

            // Create initial payment tracking record
            Payment::create([
                'order_id' => $order->id,
                'amount'   => $total,
                'status'   => PaymentStatus::Pending,
            ]);

            return $order->load(['items', 'address', 'payment']);
        });
    }
}
