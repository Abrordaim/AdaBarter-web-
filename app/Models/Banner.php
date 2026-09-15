<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $title
 * @property string $image_url
 * @property string|null $redirect_url
 * @property string|null $advertiser_name
 * @property string $position
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $expired_at
 * @property int $click_count
 * @property int $view_count
 */
#[Fillable([
    'title', 'image_url', 'redirect_url', 'advertiser_name', 'position',
    'is_active', 'started_at', 'expired_at', 'click_count', 'view_count'
])]
class Banner extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'started_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeNotExpired(Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereNull('expired_at')
              ->orWhere('expired_at', '>', now());
        });
    }
}