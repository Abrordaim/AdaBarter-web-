<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $offerer_user_id
 * @property int $offerer_item_id
 * @property int $target_user_id
 * @property int $target_item_id
 * @property float|null $cash_supplement
 * @property string|null $cash_supplement_by
 * @property string $status
 * @property bool $offerer_approved
 * @property bool $target_approved
 * @property \Illuminate\Support\Carbon|null $matched_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $rejection_reason
 */
#[Fillable([
    'offerer_user_id', 'offerer_item_id', 'target_user_id', 'target_item_id',
    'cash_supplement', 'cash_supplement_by', 'status', 'offerer_approved',
    'target_approved', 'matched_at', 'completed_at', 'rejection_reason'
])]
class Offer extends Model
{
    protected function casts(): array
    {
        return [
            'cash_supplement' => 'decimal:2',
            'offerer_approved' => 'boolean',
            'target_approved' => 'boolean',
            'matched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function offerer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'offerer_user_id');
    }

    public function targetOwner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function offererItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class, 'offerer_item_id');
    }

    public function targetItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class, 'target_item_id');
    }

    public function chats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class);
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
}