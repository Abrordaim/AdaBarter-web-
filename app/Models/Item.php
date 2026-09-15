<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $title
 * @property string $description
 * @property string $condition
 * @property string|null $desired_items
 * @property float|null $estimated_price
 * @property string|null $location
 * @property string|null $city
 * @property string $status
 * @property bool $is_boosted
 * @property \Illuminate\Support\Carbon|null $boost_expires_at
 * @property array|null $images
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
#[Fillable([
    'user_id', 'category_id', 'title', 'description', 'condition',
    'desired_items', 'estimated_price', 'location', 'city',
    'status', 'is_boosted', 'boost_expires_at', 'images'
])]
class Item extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'estimated_price' => 'decimal:2',
            'is_boosted' => 'boolean',
            'boost_expires_at' => 'datetime',
            'images' => 'array',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function offersAsTarget(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Offer::class, 'target_item_id');
    }

    public function offersAsOfferer(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Offer::class, 'offerer_item_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeByCity(Builder $query, string $city): void
    {
        $query->where('city', $city);
    }

    public function scopeBoosted(Builder $query): void
    {
        $query->where('is_boosted', true)
              ->where(function ($q) {
                  $q->whereNull('boost_expires_at')
                    ->orWhere('boost_expires_at', '>', now());
              });
    }
}