<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'amount',
        'reason',
        'status',
        'refunded_by',
        'refunded_at',
        'notes',
    ];

    protected $casts = [
        'status'      => RefundStatus::class,
        'amount'      => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isRefunded(): bool
    {
        return $this->status->isRefunded();
    }
}
