<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'tracking_number',
        'tracking_url',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isShipped(): bool
    {
        return !is_null($this->shipped_at);
    }

    public function isDelivered(): bool
    {
        return !is_null($this->delivered_at);
    }
}

