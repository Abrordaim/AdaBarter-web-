<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property float $amount
 * @property string|null $description
 * @property string $status
 * @property string|null $payment_method
 * @property string|null $payment_ref
 */
#[Fillable([
    'user_id', 'type', 'amount', 'description',
    'status', 'payment_method', 'payment_ref'
])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}