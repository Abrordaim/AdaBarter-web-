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
 * @property int|null $province_id
 * @property int|null $regency_id
 * @property int|null $district_id
 * @property string|null $province_name
 * @property string|null $regency_name
 * @property string|null $district_name
 * @property string $status
 * @property bool $is_boosted
 * @property \Illuminate\Support\Carbon|null $boost_expires_at
 * @property array|null $images
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Province|null $province
 * @property-read \App\Models\Regency|null $regency
 * @property-read \App\Models\District|null $district
 */
#[Fillable([
    'user_id', 'category_id', 'title', 'description', 'condition',
    'desired_items', 'estimated_price', 'location', 'city',
    'province_id', 'regency_id', 'district_id',
    'province_name', 'regency_name', 'district_name',
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
            'province_id' => 'integer',
            'regency_id' => 'integer',
            'district_id' => 'integer',
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

    public function province(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function regency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Regency::class);
    }

    public function district(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(District::class);
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

    public function scopeByRegency(Builder $query, int $regencyId): void
    {
        $query->where('regency_id', $regencyId);
    }

    public function scopeByProvince(Builder $query, int $provinceId): void
    {
        $query->where('province_id', $provinceId);
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