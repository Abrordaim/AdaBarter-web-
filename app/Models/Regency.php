<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $province_id
 * @property-read Province $province
 * @property-read \Illuminate\Database\Eloquent\Collection<int, District> $districts
 */
class Regency extends Model
{
    public $timestamps = false;

    protected $fillable = ['id', 'code', 'name', 'province_id'];

    public function province(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function districts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(District::class);
    }
}
