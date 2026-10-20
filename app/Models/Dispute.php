<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $reported_by
 * @property string $reason
 * @property string $description
 * @property array|null $evidence_paths
 * @property string $status
 * @property string|null $resolution
 * @property int|null $refund_amount
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $admin_note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Offer $offer
 * @property-read User $reporter
 * @property-read User|null $resolver
 */
#[Fillable([
    'offer_id',
    'reported_by',
    'reason',
    'description',
    'evidence_paths',
    'status',
    'resolution',
    'refund_amount',
    'resolved_by',
    'resolved_at',
    'admin_note',
])]
class Dispute extends Model
{
    protected function casts(): array
    {
        return [
            'evidence_paths' => 'array',
            'refund_amount' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
