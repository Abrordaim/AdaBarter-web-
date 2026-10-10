<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $name
 * @property int $duration_days
 * @property float $price
 * @property string|null $tag
 * @property string|null $badge
 * @property array|null $features
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'duration_days', 'price', 'tag', 'badge', 'features', 'is_active', 'sort_order'])]
class VipPlan extends Model
{
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price'         => 'decimal:2',
            'features'      => 'array',
            'is_active'     => 'boolean',
            'sort_order'    => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }
}
