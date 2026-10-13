<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $item_id
 * @property string $role
 */
#[Fillable(['offer_id', 'item_id', 'role'])]
class OfferItem extends Model
{
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
