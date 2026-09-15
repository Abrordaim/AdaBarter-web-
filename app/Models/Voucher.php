<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property int $quota_amount
 * @property int $max_claims
 * @property int $claimed_count
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $expired_at
 */
#[Fillable([
    'code', 'description', 'quota_amount', 'max_claims',
    'claimed_count', 'is_active', 'expired_at'
])]
class Voucher extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expired_at' => 'datetime',
        ];
    }

    public function claims(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VoucherClaim::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)
              ->whereColumn('claimed_count', '<', 'max_claims')
              ->where(function ($q) {
                  $q->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
              });
    }

    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast();
    }

    public function isClaimable(): bool
    {
        return $this->is_active && $this->claimed_count < $this->max_claims && !$this->isExpired();
    }
}