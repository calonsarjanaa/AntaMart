<?php

namespace App\Enums;

enum CancellationStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending Review',
            self::Approved  => 'Approved',
            self::Rejected  => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Whether the Store Owner has already made a decision.
     * Used to enforce first-committed-wins on concurrent withdrawal (TBD-06).
     */
    public function isDecided(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }
}

