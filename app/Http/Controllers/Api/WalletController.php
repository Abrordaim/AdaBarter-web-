<?php

namespace App\Http\Controllers\Api;

use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends BaseApiController
{
    public function __construct(protected WalletService $walletService) {}

    /**
     * GET /api/wallet
     * Menampilkan saldo & riwayat mutasi (30 terakhir).
     */
    public function index(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getWallet($request->user());

        return $this->sendResponse([
            'balance'      => $wallet->balance,
            'transactions' => $wallet->transactions->map(fn ($t) => [
                'id'             => $t->id,
                'type'           => $t->type,
                'amount'         => $t->amount,
                'reason'         => $t->reason,
                'balance_after'  => $t->balance_after,
                'reference_type' => $t->reference_type,
                'reference_id'   => $t->reference_id,
                'created_at'     => $t->created_at?->toISOString(),
            ]),
        ], 'Data dompet berhasil diambil.');
    }

    /**
     * POST /api/wallet/withdraw
     * Mengajukan penarikan saldo ke rekening bank.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'         => 'required|integer|min:' . WalletService::MIN_WITHDRAWAL,
            'bank_name'      => 'required|string|max:50',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'required|string|max:100',
        ]);

        try {
            $withdrawal = $this->walletService->requestWithdrawal(
                user:          $request->user(),
                amount:        $validated['amount'],
                bankName:      $validated['bank_name'],
                accountNumber: $validated['account_number'],
                accountName:   $validated['account_name'],
            );

            return $this->sendResponse([
                'withdrawal' => [
                    'id'             => $withdrawal->id,
                    'amount'         => $withdrawal->amount,
                    'bank_name'      => $withdrawal->bank_name,
                    'account_number' => $withdrawal->account_number,
                    'account_name'   => $withdrawal->account_name,
                    'status'         => $withdrawal->status,
                    'created_at'     => $withdrawal->created_at?->toISOString(),
                ],
            ], 'Permintaan penarikan berhasil diajukan. Tunggu konfirmasi admin dalam 1×24 jam.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * GET /api/wallet/withdrawals
     * Daftar riwayat penarikan user yang sedang login.
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $withdrawals = $request->user()
            ->withdrawals()
            ->latest()
            ->paginate(20);

        return $this->sendResponse(
            $withdrawals->through(fn ($w) => [
                'id'               => $w->id,
                'amount'           => $w->amount,
                'bank_name'        => $w->bank_name,
                'account_number'   => $w->account_number,
                'account_name'     => $w->account_name,
                'status'           => $w->status,
                'rejection_reason' => $w->rejection_reason,
                'processed_at'     => $w->processed_at?->toISOString(),
                'created_at'       => $w->created_at?->toISOString(),
            ]),
            'Riwayat penarikan berhasil diambil.'
        );
    }
}
