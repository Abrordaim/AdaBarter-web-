<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $user_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $status 'idle', 'otw', 'arrived'
 * @property bool $is_sharing
 * @property int|null $distance_to_target_meters
 * @property float|null $heading
 * @property float|null $speed
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property-read Offer $offer
 * @property-read User $user
 */
#[Fillable([
    'offer_id',
    'user_id',
    'latitude',
    'longitude',
    'status',
    'is_sharing',
    'distance_to_target_meters',
    'heading',
    'speed',
    'last_seen_at',
])]
class BarterTracking extends Model
{
    protected $table = 'barter_trackings';

    protected function casts(): array
    {
        return [
            'latitude'                   => 'float',
            'longitude'                  => 'float',
            'is_sharing'                 => 'boolean',
            'distance_to_target_meters'  => 'integer',
            'heading'                    => 'float',
            'speed'                      => 'float',
            'last_seen_at'               => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'offer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Helper to calculate distance in meters using Haversine formula
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000; // in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}
