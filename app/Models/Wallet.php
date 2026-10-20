<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * @property int $id
 * @property int $user_id
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WalletTransaction> $transactions
 */
#[Fillable(['user_id', 'balance'])]
class Wallet extends Model
{
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Menambahkan saldo ke dompet secara aman dan mencatat ke ledger append-only.
     *
     * @param int $amount
     * @param string $reason
     * @param Model|null $reference
     * @return WalletTransaction
     */
    public function credit(int $amount, string $reason, ?Model $reference = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal credit harus bernilai positif lebih dari 0.');
        }

        return DB::transaction(function () use ($amount, $reason, $reference) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $newBalance = $lockedWallet->balance + $amount;
            $lockedWallet->update(['balance' => $newBalance]);

            return $lockedWallet->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'reason' => $reason,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'balance_after' => $newBalance,
            ]);
        });
    }

    /**
     * Mengurangi saldo dompet secara aman dan mencatat ke ledger append-only.
     * Mencegah saldo negatif secara ketat.
     *
     * @param int $amount
     * @param string $reason
     * @param Model|null $reference
     * @return WalletTransaction
     */
    public function debit(int $amount, string $reason, ?Model $reference = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal debit harus bernilai positif lebih dari 0.');
        }

        return DB::transaction(function () use ($amount, $reason, $reference) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            if ($lockedWallet->balance < $amount) {
                throw new RuntimeException("Saldo tidak mencukupi untuk melakukan debit. Saldo saat ini: Rp " . number_format($lockedWallet->balance, 0, ',', '.') . ", dibutuhkan: Rp " . number_format($amount, 0, ',', '.'));
            }

            $newBalance = $lockedWallet->balance - $amount;
            $lockedWallet->update(['balance' => $newBalance]);

            return $lockedWallet->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'reason' => $reason,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'balance_after' => $newBalance,
            ]);
        });
    }
}
