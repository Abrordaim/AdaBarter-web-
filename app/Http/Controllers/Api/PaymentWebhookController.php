<?php

namespace App\Http\Controllers\Api;

use App\Services\PaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends BaseApiController
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Webhook notifikasi pembayaran resmi dari Midtrans.
     * Tidak memerlukan CSRF maupun autentikasi user, tetapi dilindungi verifikasi signature SHA-512.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        try {
            $result = $this->paymentService->handleNotification($payload);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ], 200);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
