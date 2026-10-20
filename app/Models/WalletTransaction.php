<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $type
 * @property int $amount
 * @property string $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int $balance_after
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Wallet $wallet
 * @property-read Model|null $reference
 */
#[Fillable([
    'wallet_id',
    'type',
    'amount',
    'reason',
    'reference_type',
    'reference_id',
    'balance_after',
])]
class WalletTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * Perlindungan ketat: Ledger bersifat append-only, dilarang diubah atau dihapus.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Baris ledger transaksi dompet dilarang diubah (append-only audit log).');
        });

        static::deleting(function () {
            throw new RuntimeException('Baris ledger transaksi dompet dilarang dihapus (append-only audit log).');
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
