<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $regency_id
 * @property-read Regency $regency
 */
class District extends Model
{
    public $timestamps = false;

    protected $fillable = ['id', 'code', 'name', 'regency_id'];

    public function regency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Regency::class);
    }
}
