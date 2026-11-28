<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'birth_date',
        'recipient_name',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'district',
        'village',
        'province',
        'postal_code',
        'latitude',
        'longitude',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date'        => 'date',
        'latitude'          => 'float',
        'longitude'         => 'float',
    ];

    public function hasBirthDate(): bool
    {
        return $this->birth_date !== null;
    }

    public function meetsAgeRequirement(int $minAge): bool
    {
        return $this->birth_date !== null && $this->birth_date->age >= $minAge;
    }

    /**
     * Return profile shipping address formatted for checkout auto-fill, or null if incomplete.
     *
     * @return array<string, string>|null
     */
    public function profileAddress(): ?array
    {
        $hasAddress = !empty($this->address_line1)
            || !empty($this->phone)
            || !empty($this->city)
            || !empty($this->province)
            || !empty($this->postal_code);

        if (!$hasAddress) {
            return null;
        }

        return [
            'recipient_name' => (string) ($this->recipient_name ?: $this->name),
            'phone'          => (string) ($this->phone ?? ''),
            'province'       => (string) ($this->province ?? ''),
            'city'           => (string) ($this->city ?? ''),
            'district'       => (string) ($this->district ?? ''),
            'village'        => (string) ($this->village ?? ''),
            'address_line1'  => (string) ($this->address_line1 ?? ''),
            'address_line2'  => (string) ($this->address_line2 ?? ''),
            'postal_code'    => (string) ($this->postal_code ?? ''),
            'latitude'       => $this->latitude !== null ? (string) $this->latitude : '',
            'longitude'      => $this->longitude !== null ? (string) $this->longitude : '',
        ];
    }

    public function isStoreOwner(): bool
    {
        return $this->role === 'store_owner';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class, 'requested_by');
    }

    public function cancellationDecisions(): HasMany
    {
        return $this->hasMany(CancellationRequest::class, 'decided_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'refunded_by');
    }
}
