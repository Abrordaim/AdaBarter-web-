<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $days
 * @property float $price
 * @property string|null $tag
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'days', 'price', 'tag', 'description', 'is_active', 'sort_order'])]
class BoostPackage extends Model
{
    protected function casts(): array
    {
        return [
            'days'       => 'integer',
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
