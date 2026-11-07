<?php

namespace App\Models;

use App\Enums\CancellationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'requested_by',
        'reason',
        'status',
        'rejection_reason',
        'decided_by',
        'decided_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'status'       => CancellationStatus::class,
        'decided_at'   => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === CancellationStatus::Pending;
    }

    public function isDecided(): bool
    {
        return $this->status->isDecided();
    }
}
