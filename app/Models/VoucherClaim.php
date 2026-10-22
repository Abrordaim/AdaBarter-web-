<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $voucher_id
 * @property \Illuminate\Support\Carbon $claimed_at
 * @property bool $is_used
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property int|null $offer_id
 * @property-read User $user
 * @property-read Voucher $voucher
 * @property-read Offer|null $offer
 */
#[Fillable(['user_id', 'voucher_id', 'claimed_at', 'is_used', 'used_at', 'offer_id'])]
class VoucherClaim extends Model
{
    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_used', false);
    }

    public function scopeUsed(Builder $query): void
    {
        $query->where('is_used', true);
    }
}