<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $proposed_by
 * @property float $latitude
 * @property float $longitude
 * @property string $place_name
 * @property string|null $address
 * @property \Illuminate\Support\Carbon $meet_at
 * @property string $status 'proposed', 'agreed', 'locked', 'superseded'
 * @property int|null $agreed_by
 * @property \Illuminate\Support\Carbon|null $agreed_by_other_at
 * @property \Illuminate\Support\Carbon|null $locked_at
 * @property string|null $notes
 * @property-read Offer $offer
 * @property-read User $proposer
 * @property-read User|null $agreer
 * @property-read int $barter_transaction_id
 * @property-read string $maps_url
 */
#[Fillable([
    'offer_id',
    'proposed_by',
    'latitude',
    'longitude',
    'place_name',
    'address',
    'meet_at',
    'status',
    'agreed_by',
    'agreed_by_other_at',
    'locked_at',
    'notes',
])]
class MeetingPoint extends Model
{
    protected $table = 'meeting_points';

    protected function casts(): array
    {
        return [
            'latitude'           => 'float',
            'longitude'          => 'float',
            'meet_at'            => 'datetime',
            'agreed_by_other_at' => 'datetime',
            'locked_at'          => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'offer_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function agreer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agreed_by');
    }

    public function getBarterTransactionIdAttribute(): int
    {
        return $this->offer_id;
    }

    public function getMapsUrlAttribute(): string
    {
        return "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";
    }

    public function isProposed(): bool
    {
        return $this->status === 'proposed';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
    }
}
