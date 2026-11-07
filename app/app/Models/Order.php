<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'customer_id',
        'guest_email',
        'guest_phone',
        'status',
        'subtotal',
        'shipping_cost',
        'total',
        'idempotency_key',
        'expires_at',
    ];

    protected $casts = [
        'status'        => OrderStatus::class,
        'subtotal'      => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total'         => 'decimal:2',
        'expires_at'    => 'datetime',
    ];

    public function isGuest(): bool
    {
        return is_null($this->customer_id);
    }

    public function canTransitionTo(OrderStatus $next): bool
    {
        return $this->status->canTransitionTo($next);
    }

    /**
     * Transition order status strictly adhering to the state machine (§6.1).
     *
     * @throws InvalidOrderStateException
     */
    public function transitionTo(OrderStatus $next): void
    {
        if (!$this->canTransitionTo($next)) {
            throw new InvalidOrderStateException($this->status, $next);
        }

        $this->status = $next;
        $this->save();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function address(): HasOne
    {
        return $this->hasOne(OrderAddress::class, 'order_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'order_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'order_id');
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class, 'order_id');
    }

    public function latestCancellationRequest(): HasOne
    {
        return $this->hasOne(CancellationRequest::class, 'order_id')->latestOfMany();
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class, 'order_id');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'order_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'order_id');
    }
}

