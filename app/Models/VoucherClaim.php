<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $voucher_id
 * @property \Illuminate\Support\Carbon $claimed_at
 */
#[Fillable(['user_id', 'voucher_id', 'claimed_at'])]
class VoucherClaim extends Model
{
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}