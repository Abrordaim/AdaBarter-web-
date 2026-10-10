<?php

namespace App\Http\Controllers\Api;

use App\Services\MonetizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MonetizationController extends BaseApiController 
{
    public function __construct(
        protected MonetizationService $monetizationService
    ) {}

    public function plans(): JsonResponse
    {
        return $this->sendResponse([
            'subscription_plans' => $this->monetizationService->getSubscriptionPlans(),
            'boost_packages' => $this->monetizationService->getBoostPackages(),
            'quota_packages' => $this->monetizationService->getQuotaPackages(),
        ], 'Daftar paket monetisasi berhasil diambil.');
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'        => 'required|integer|exists:vip_plans,id',
            'payment_method' => 'nullable|string',
        ]);

        try {
            $result = $this->monetizationService->subscribe(
                $request->user(),
                (int) $request->input('plan_id'),
                $request->input('payment_method', 'QRIS / Virtual Account')
            );

            return $this->sendResponse($result, 'Selamat! Anda telah resmi menjadi anggota VIP AdaBarter. Nikmati kuota posting tanpa batas!');
        } catch (ValidationException $e) {
            return $this->sendError('Langganan gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Terjadi kesalahan: ' . $e->getMessage(), [], 500);
        }
    }

    public function boost(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'boost_package_id' => 'required_without:days|nullable|integer|exists:boost_packages,id',
            'days'             => 'required_without:boost_package_id|nullable|integer',
            'payment_method'   => 'nullable|string',
        ]);

        try {
            $boostPackageId = $request->has('boost_package_id') ? (int) $request->input('boost_package_id') : null;
            $days           = $request->has('days') ? (int) $request->input('days') : null;

            $result = $this->monetizationService->boostItem(
                $request->user(),
                $id,
                $boostPackageId,
                $days,
                $request->input('payment_method', 'QRIS / Virtual Account')
            );

            return $this->sendResponse($result, 'Barang berhasil di-boost dan akan tampil di posisi teratas etalase!');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal memproses boost: ' . $e->getMessage(), [], 400);
        }
    }

    public function purchaseQuota(Request $request): JsonResponse
    {
        $request->validate([
            'slot_package_id' => 'required|integer|exists:slot_packages,id',
            'payment_method'  => 'nullable|string',
        ]);

        try {
            $result = $this->monetizationService->purchaseQuota(
                $request->user(),
                (int) $request->input('slot_package_id'),
                $request->input('payment_method', 'QRIS / Virtual Account')
            );

            return $this->sendResponse($result, 'Pembelian slot posting berhasil! Kuota postingan Anda telah bertambah.');
        } catch (\Exception $e) {
            return $this->sendError('Gagal membeli kuota: ' . $e->getMessage(), [], 500);
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->monetizationService->getUserTransactions($request->user());

        return $this->sendResponse($transactions, 'Riwayat transaksi berhasil diambil.');
    }
}
