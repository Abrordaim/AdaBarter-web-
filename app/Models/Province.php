<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Regency> $regencies
 */
class Province extends Model
{
    public $timestamps = false;

    protected $fillable = ['id', 'code', 'name'];

    public function regencies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Regency::class);
    }
}
