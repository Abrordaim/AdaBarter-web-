<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ClaimVoucherRequest;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoucherController extends BaseApiController
{
    public function __construct(
        protected VoucherService $voucherService
    ) {}

    public function available(Request $request): JsonResponse
    {
        try {
            $vouchers = $this->voucherService->getAvailablePublicVouchers($request->user());

            return $this->sendResponse(
                $vouchers,
                'Daftar voucher publik yang tersedia berhasil dimuat.'
            );
        } catch (\Throwable $e) {
            return $this->sendError('Gagal memuat daftar voucher: ' . $e->getMessage(), [], 500);
        }
    }

    public function myVouchers(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status'); // 'active' | 'used' | null
            $vouchers = $this->voucherService->getMyVouchers($request->user(), $status);

            return $this->sendResponse(
                $vouchers,
                'Daftar voucher milik Anda berhasil dimuat.'
            );
        } catch (\Throwable $e) {
            return $this->sendError('Gagal memuat voucher Anda: ' . $e->getMessage(), [], 500);
        }
    }

    public function claim(ClaimVoucherRequest $request): JsonResponse
    {
        try {
            $result = $this->voucherService->claimVoucher(
                $request->user(),
                $request->input('code'),
                $request->input('voucher_id') ? (int) $request->input('voucher_id') : null
            );

            return $this->sendResponse(
                $result,
                'Selamat! Voucher diskon berhasil diklaim dan siap digunakan.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Klaim voucher gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengklaim voucher: ' . $e->getMessage(), [], 500);
        }
    }
}
