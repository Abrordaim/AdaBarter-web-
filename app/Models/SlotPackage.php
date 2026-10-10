<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $name
 * @property int $slots
 * @property float $price
 * @property string|null $badge
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'slots', 'price', 'badge', 'is_active', 'sort_order'])]
class SlotPackage extends Model
{
    protected function casts(): array
    {
        return [
            'slots'      => 'integer',
            'price'      => 'decimal:2',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
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
