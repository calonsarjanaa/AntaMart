<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (empty($order->id)) {
                $order->id = static::generateOrderCode();
            }
        });
    }

    /**
     * Generate sequential daily order code in format: AM.{YYYY}{MM}{DD}{NNNN}
     * Example: AM.202611280001 (resets to 0001 each day).
     */
    public static function generateOrderCode(): string
    {
        $date = now()->timezone(config('app.timezone'))->format('Ymd');
        $prefix = "AM.{$date}";

        $lastId = DB::table('orders')
            ->where('id', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('id');

        $nextNumber = 1;
        if (is_string($lastId) && str_starts_with($lastId, $prefix)) {
            $suffix = substr($lastId, strlen($prefix));
            if (ctype_digit($suffix)) {
                $nextNumber = ((int) $suffix) + 1;
            }
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

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
        'rejection_reason',
        'confirmed_at',
        'rejected_at',
    ];

    protected $casts = [
        'status'        => OrderStatus::class,
        'subtotal'      => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total'         => 'decimal:2',
        'expires_at'    => 'datetime',
        'confirmed_at'  => 'datetime',
        'rejected_at'   => 'datetime',
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

