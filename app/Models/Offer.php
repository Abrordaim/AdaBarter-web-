<?php

namespace App\Models;

use App\Enums\EscrowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $offerer_user_id
 * @property int $offerer_item_id
 * @property int $target_user_id
 * @property int $target_item_id
 * @property float|null $cash_supplement
 * @property string|null $cash_supplement_by
 * @property string $payment_method
 * @property int $price_difference
 * @property int|null $payer_id
 * @property int|null $receiver_id
 * @property EscrowStatus|null $escrow_status
 * @property \Illuminate\Support\Carbon|null $payer_confirmed_at
 * @property \Illuminate\Support\Carbon|null $receiver_confirmed_at
 * @property \Illuminate\Support\Carbon|null $confirm_deadline_at
 * @property \Illuminate\Support\Carbon|null $released_at
 * @property string|null $handover_code
 * @property int $handover_attempts
 * @property string $status
 * @property bool $offerer_approved
 * @property bool $target_approved
 * @property \Illuminate\Support\Carbon|null $matched_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $rejection_reason
 * @property-read User $offerer
 * @property-read User $targetOwner
 * @property-read User|null $payer
 * @property-read User|null $receiver
 * @property-read Item|null $offererItem
 * @property-read Item|null $targetItem
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Item> $offererItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Payment> $payments
 * @property-read Payment|null $latestPayment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Dispute> $disputes
 */
#[Fillable([
    'offerer_user_id', 'offerer_item_id', 'target_user_id', 'target_item_id',
    'cash_supplement', 'cash_supplement_by',
    'payment_method', 'price_difference', 'payer_id', 'receiver_id', 'escrow_status',
    'payer_confirmed_at', 'receiver_confirmed_at', 'confirm_deadline_at', 'released_at',
    'handover_code', 'handover_attempts',
    'status', 'offerer_approved', 'target_approved', 'matched_at', 'completed_at', 'rejection_reason',
])]
class Offer extends Model
{
    protected function casts(): array
    {
        return [
            'cash_supplement' => 'decimal:2',
            'price_difference' => 'integer',
            'escrow_status' => EscrowStatus::class,
            'payer_confirmed_at' => 'datetime',
            'receiver_confirmed_at' => 'datetime',
            'confirm_deadline_at' => 'datetime',
            'released_at' => 'datetime',
            'handover_attempts' => 'integer',
            'offerer_approved' => 'boolean',
            'target_approved' => 'boolean',
            'matched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function offerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offerer_user_id');
    }

    public function targetOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function offererItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'offerer_item_id');
    }

    public function offererItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'offer_items', 'offer_id', 'item_id')
            ->wherePivot('role', 'offerer')
            ->withTimestamps();
    }

    public function targetItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'target_item_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function activeDispute(): HasOne
    {
        return $this->hasOne(Dispute::class)->where('status', 'open');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isEscrow(): bool
    {
        return $this->payment_method === 'gateway';
    }

    public function isCod(): bool
    {
        return $this->payment_method === 'cod';
    }

    /**
     * Menghitung total estimasi harga barang penawar.
     */
    public function totalOffererEstimatedPrice(): float
    {
        if ($this->relationLoaded('offererItems') && $this->offererItems->isNotEmpty()) {
            return (float) $this->offererItems->sum(fn ($item) => (float) $item->estimated_price);
        }

        $items = $this->offererItems()->get();
        if ($items->isNotEmpty()) {
            return (float) $items->sum(fn ($item) => (float) $item->estimated_price);
        }

        return (float) ($this->offererItem?->estimated_price ?? 0);
    }

    /**
     * Menghitung total estimasi harga barang target.
     */
    public function totalTargetEstimatedPrice(): float
    {
        return (float) ($this->targetItem?->estimated_price ?? 0);
    }

    /**
     * Hitung selisih harga dan tentukan pihak payer / receiver.
     * Pihak dengan total estimasi lebih kecil adalah "payer", pihak dengan total lebih besar adalah "receiver".
     *
     * @return array{price_difference: int, payer_id: int|null, receiver_id: int|null}
     */
    public function calculatePriceDifferenceAndParties(): array
    {
        $offererTotal = (int) round($this->totalOffererEstimatedPrice());
        $targetTotal = (int) round($this->totalTargetEstimatedPrice());

        $diff = abs($offererTotal - $targetTotal);

        if ($offererTotal < $targetTotal) {
            // Penawar punya barang bernilai lebih kecil -> penawar harus bayar selisih
            $payerId = $this->offerer_user_id;
            $receiverId = $this->target_user_id;
        } elseif ($offererTotal > $targetTotal) {
            // Pemilik target punya barang bernilai lebih kecil -> pemilik target harus bayar selisih
            $payerId = $this->target_user_id;
            $receiverId = $this->offerer_user_id;
        } else {
            // Nilai seimbang / barter murni sepadan
            $payerId = null;
            $receiverId = null;
        }

        return [
            'price_difference' => $diff,
            'payer_id' => $payerId,
            'receiver_id' => $receiverId,
        ];
    }
}