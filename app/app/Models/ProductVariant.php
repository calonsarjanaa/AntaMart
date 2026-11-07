<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'stock_physical',
        'stock_reserved',
        'is_active',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'stock_physical' => 'integer',
        'stock_reserved' => 'integer',
        'is_active'      => 'boolean',
    ];

    /**
     * FR-INV-002 — Available Stock = Physical Stock - Reserved Stock
     */
    protected function availableStock(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, $this->stock_physical - $this->stock_reserved),
        );
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'product_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id');
    }
}
