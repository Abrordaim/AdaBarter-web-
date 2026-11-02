<?php

namespace App\Http\Controllers\Api;

use App\Services\MonetizationService;
use App\Services\PaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MonetizationController extends BaseApiController 
{
    public function __construct(
        protected MonetizationService $monetizationService,
        protected PaymentService $paymentService
    ) {}

    public function plans(): JsonResponse
    {
        return $this->sendResponse([
            'subscription_plans' => $this->monetizationService->getSubscriptionPlans(),
            'boost_packages'     => $this->monetizationService->getBoostPackages(),
        ], 'Daftar paket monetisasi berhasil diambil.');
    }

    /**
     * Membuat Snap Token Midtrans untuk berlangganan VIP.
     */
    public function payVip(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:vip_plans,id',
        ]);

        try {
            $result = $this->paymentService->createVipSnapToken(
                $request->user(),
                (int) $request->input('plan_id')
            );

            return $this->sendResponse($result, 'Token pembayaran Midtrans untuk langganan VIP berhasil dibuat.');
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal memproses pembayaran VIP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Membuat Snap Token Midtrans untuk membeli paket iklan sorotan (boost).
     */
    public function payBoost(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'boost_package_id' => 'required|integer|exists:boost_packages,id',
        ]);

        try {
            $result = $this->paymentService->createBoostSnapToken(
                $request->user(),
                $id,
                (int) $request->input('boost_package_id')
            );

            return $this->sendResponse($result, 'Token pembayaran Midtrans untuk iklan sorotan (boost) berhasil dibuat.');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal memproses pembayaran boost: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Boost item langsung menggunakan kuota boost VIP gratis milik user.
     */
    public function boostWithQuota(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->monetizationService->boostWithQuota(
                $request->user(),
                $id
            );

            return $this->sendResponse($result, 'Barang berhasil di-boost menggunakan 1 Kuota Boost VIP Anda!');
        } catch (ValidationException $e) {
            return $this->sendError('Gagal memproses boost.', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Simulasi langganan VIP langsung (demo / testing).
     */
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

            return $this->sendResponse($result, 'Selamat! Anda telah resmi menjadi anggota VIP AdaBarter.');
        } catch (ValidationException $e) {
            return $this->sendError('Langganan gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Terjadi kesalahan: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Simulasi boost item langsung (demo / testing).
     */
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

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->monetizationService->getUserTransactions($request->user());

        return $this->sendResponse($transactions, 'Riwayat transaksi berhasil diambil.');
    }
}
