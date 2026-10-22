<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int|null $voucher_claim_id
 * @property string $order_id
 * @property int|null $original_amount
 * @property int $discount_amount
 * @property int $gross_amount
 * @property string $gateway
 * @property string|null $gateway_transaction_id
 * @property string|null $payment_type
 * @property string $status
 * @property string|null $snap_token
 * @property array|null $raw_payload
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Offer $offer
 * @property-read VoucherClaim|null $voucherClaim
 */
#[Fillable([
    'offer_id',
    'voucher_claim_id',
    'order_id',
    'original_amount',
    'discount_amount',
    'gross_amount',
    'gateway',
    'gateway_transaction_id',
    'payment_type',
    'status',
    'snap_token',
    'raw_payload',
    'paid_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'original_amount' => 'integer',
            'discount_amount' => 'integer',
            'gross_amount' => 'integer',
            'raw_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function voucherClaim(): BelongsTo
    {
        return $this->belongsTo(VoucherClaim::class);
    }

    public function isSettled(): bool
    {
        return $this->status === 'settlement';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
