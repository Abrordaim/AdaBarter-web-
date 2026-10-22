<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property string $type 'fixed' | 'percentage'
 * @property int|null $discount_amount
 * @property int|null $percentage
 * @property int|null $max_discount_amount
 * @property int $min_transaction_amount
 * @property string $distribution 'public' | 'private'
 * @property int|null $quota_amount
 * @property int $max_claims
 * @property int $claimed_count
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $expired_at
 */
#[Fillable([
    'code', 'description', 'type', 'discount_amount',
    'percentage', 'max_discount_amount', 'min_transaction_amount',
    'distribution', 'quota_amount', 'max_claims',
    'claimed_count', 'is_active', 'expired_at'
])]
class Voucher extends Model
{
    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
            'percentage' => 'integer',
            'max_discount_amount' => 'integer',
            'min_transaction_amount' => 'integer',
            'quota_amount' => 'integer',
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

    public function scopePublic(Builder $query): void
    {
        $query->where('distribution', 'public');
    }

    public function scopePrivate(Builder $query): void
    {
        $query->where('distribution', 'private');
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

    /**
     * Hitung besaran diskon berdasarkan nominal transaksi selisih barter.
     */
    public function calculateDiscount(int $amount): int
    {
        if ($amount < $this->min_transaction_amount) {
            return 0;
        }

        if ($this->type === 'percentage') {
            $discount = (int) floor($amount * (($this->percentage ?? 0) / 100));
            if ($this->max_discount_amount && $this->max_discount_amount > 0) {
                $discount = min($discount, (int) $this->max_discount_amount);
            }
            return min($discount, $amount);
        }

        return min((int) ($this->discount_amount ?? 0), $amount);
    }

    /**
     * Format label diskon untuk tampilan.
     */
    public function formattedDiscount(): string
    {
        if ($this->type === 'percentage') {
            $str = $this->percentage . '%';
            if ($this->max_discount_amount) {
                $str .= ' (Maks. Rp ' . number_format($this->max_discount_amount, 0, ',', '.') . ')';
            }
            return $str;
        }

        return 'Rp ' . number_format($this->discount_amount ?? 0, 0, ',', '.');
    }
}