<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentExpired;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $orderId
    ) {}

    public function handle(InventoryService $inventoryService): void
    {
        DB::transaction(function () use ($inventoryService): void {
            $order = Order::whereKey($this->orderId)->lockForUpdate()->first();

            if (!$order || $order->status !== OrderStatus::PendingPayment || !$order->expires_at || $order->expires_at->isFuture()) {
                return;
            }

            $payment = $order->payment()->lockForUpdate()->first();

            if (!$payment || $payment->status !== PaymentStatus::Pending) {
                return;
            }

            $order->transitionTo(OrderStatus::Expired);
            $payment->status = PaymentStatus::Expired;
            $payment->save();

            $inventoryService->releaseReservations($order);

            PaymentExpired::dispatch($order);
        });
    }
}
