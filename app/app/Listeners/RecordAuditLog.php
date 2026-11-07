<?php

namespace App\Listeners;

use App\Events\AuditEvent;
use App\Events\PaymentExpired;
use App\Services\AuditService;

class RecordAuditLog
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function handle(AuditEvent $event): void
    {
        $this->auditService->record(
            event: $event->name,
            orderId: $event->orderId,
            actorId: $event->actorId,
            actorType: $event->actorType,
            payload: $event->payload
        );
    }

    public function handlePaymentExpired(PaymentExpired $event): void
    {
        $this->auditService->record(
            event: 'payment.expired',
            orderId: $event->order->id,
            actorId: null,
            actorType: 'system',
            payload: ['order_status' => $event->order->status->value]
        );
    }
}
