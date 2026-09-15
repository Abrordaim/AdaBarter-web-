<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property string $plan
 * @property float|null $price_paid
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon $expired_at
 * @property bool $is_active
 */
#[Fillable(['user_id', 'plan', 'price_paid', 'started_at', 'expired_at', 'is_active'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'started_at' => 'datetime',
            'expired_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
              ->where('expired_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expired_at->isPast();
    }
}