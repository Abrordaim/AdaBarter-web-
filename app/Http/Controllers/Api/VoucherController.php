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

    public function claim(ClaimVoucherRequest $request): JsonResponse
    {
        try {
            $result = $this->voucherService->claimVoucher(
                $request->user(),
                $request->input('code')
            );

            return $this->sendResponse(
                $result,
                'Voucher berhasil diklaim! Kuota postingan Anda bertambah.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Klaim voucher gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengklaim voucher: ' . $e->getMessage(), [], 500);
        }
    }
}
