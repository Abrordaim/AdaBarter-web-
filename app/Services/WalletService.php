<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /** Minimum nominal penarikan: Rp 50.000 */
    public const MIN_WITHDRAWAL = 50_000;

    /**
     * Mengambil atau membuat dompet user, lengkap dengan saldo & riwayat mutasi.
     */
    public function getWallet(User $user): Wallet
    {
        return $user->getOrCreateWallet()->load(['transactions' => fn ($q) => $q->latest()->take(30)]);
    }

    /**
     * Mengajukan permohonan penarikan dana dari dompet.
     * Saldo didebit segera (ditahan), withdrawal berstatus 'pending'.
     *
     * @return Withdrawal
     */
    public function requestWithdrawal(
        User $user,
        int $amount,
        string $bankName,
        string $accountNumber,
        string $accountName
    ): Withdrawal {
        if ($amount < self::MIN_WITHDRAWAL) {
            throw new DomainException(
                'Nominal penarikan minimum adalah Rp ' . number_format(self::MIN_WITHDRAWAL, 0, ',', '.') . '.'
            );
        }

        return DB::transaction(function () use ($user, $amount, $bankName, $accountNumber, $accountName) {
            $wallet = $user->getOrCreateWallet();

            // Debit saldo (ditahan) — akan dikembalikan jika rejected
            $wallet->debit($amount, 'withdrawal_pending');

            // Buat record withdrawal
            return Withdrawal::create([
                'user_id'        => $user->id,
                'amount'         => $amount,
                'bank_name'      => $bankName,
                'account_number' => $accountNumber,
                'account_name'   => $accountName,
                'status'         => 'pending',
            ]);
        });
    }

    /**
     * Menyetujui dan menandai penarikan sebagai telah ditransfer (paid).
     * Admin mengeksekusi ini setelah transfer bank manual dilakukan.
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function approveWithdrawal(User $admin, Withdrawal $withdrawal): Withdrawal
    {
        if (!$admin->isAdmin()) {
            throw new AuthorizationException('Hanya admin yang dapat menyetujui penarikan.');
        }

        if (!$withdrawal->isPending()) {
            throw new DomainException("Penarikan #$withdrawal->id tidak dalam status pending (saat ini: $withdrawal->status).");
        }

        return DB::transaction(function () use ($admin, $withdrawal) {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if (!$locked->isPending()) {
                throw new DomainException('Penarikan ini telah diproses oleh sesi lain.');
            }

            $locked->update([
                'status'       => 'paid',
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            return $locked->fresh(['user', 'processor']);
        });
    }

    /**
     * Menolak penarikan dan mengembalikan saldo ke dompet user.
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function rejectWithdrawal(User $admin, Withdrawal $withdrawal, string $reason): Withdrawal
    {
        if (!$admin->isAdmin()) {
            throw new AuthorizationException('Hanya admin yang dapat menolak penarikan.');
        }

        if (!$withdrawal->isPending()) {
            throw new DomainException("Penarikan #$withdrawal->id tidak dalam status pending (saat ini: $withdrawal->status).");
        }

        return DB::transaction(function () use ($admin, $withdrawal, $reason) {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->firstOrFail();

            if (!$locked->isPending()) {
                throw new DomainException('Penarikan ini telah diproses oleh sesi lain.');
            }

            // Kembalikan saldo ke dompet user
            $user = User::findOrFail($locked->user_id);
            $user->getOrCreateWallet()->credit($locked->amount, 'withdrawal_rejected', $locked);

            $locked->update([
                'status'           => 'rejected',
                'processed_by'     => $admin->id,
                'processed_at'     => now(),
                'rejection_reason' => $reason,
            ]);

            return $locked->fresh(['user', 'processor']);
        });
    }
}
