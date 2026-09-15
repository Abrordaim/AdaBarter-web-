<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $sender_id
 * @property string $message
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $read_at
 */
#[Fillable(['offer_id', 'sender_id', 'message', 'type', 'read_at'])]
class Chat extends Model
{
    protected $table = 'chats';

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function offer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function sender(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}