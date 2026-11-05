<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Processing     = 'processing';
    case Cancelled      = 'cancelled';
    case Shipped        = 'shipped';
    case Completed      = 'completed';
    case Expired        = 'expired';

    /**
     * Returns the allowed next statuses from the current state.
     * Enforces the state machine defined in PRD §6.1.
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::PendingPayment => [self::Processing, self::Expired],
            self::Processing     => [self::Cancelled, self::Shipped],
            self::Shipped        => [self::Completed],
            self::Cancelled,
            self::Completed,
            self::Expired        => [], // Terminal states
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match($this) {
            self::PendingPayment => 'Pending Payment',
            self::Processing     => 'Processing',
            self::Cancelled      => 'Cancelled',
            self::Shipped        => 'Shipped',
            self::Completed      => 'Completed',
            self::Expired        => 'Expired',
        };
    }
}

