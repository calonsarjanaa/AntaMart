<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'xendit_invoice_id',
        'xendit_payment_url',
        'amount',
        'status',
        'paid_at',
        'expired_at',
        'late_payment_detected_at',
    ];

    protected $casts = [
        'status'                   => PaymentStatus::class,
        'amount'                   => 'decimal:2',
        'paid_at'                  => 'datetime',
        'expired_at'               => 'datetime',
        'late_payment_detected_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isExpired(): bool
    {
        return $this->status === PaymentStatus::Expired;
    }
}

