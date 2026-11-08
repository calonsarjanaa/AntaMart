<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Xendit = 'xendit';
    case CashOnDelivery = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::Xendit => 'Xendit',
            self::CashOnDelivery => 'Bayar di tempat (COD)',
        };
    }
}
