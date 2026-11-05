<?php

namespace App\Enums;

enum RefundStatus: string
{
    case NotRequested         = 'not_requested';
    case ManualRefundPending  = 'manual_refund_pending';
    case Refunded             = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::NotRequested        => 'Not Requested',
            self::ManualRefundPending => 'Manual Refund Pending',
            self::Refunded            => 'Refunded',
        };
    }

    public function isPending(): bool
    {
        return $this === self::ManualRefundPending;
    }

    public function isRefunded(): bool
    {
        return $this === self::Refunded;
    }
}

