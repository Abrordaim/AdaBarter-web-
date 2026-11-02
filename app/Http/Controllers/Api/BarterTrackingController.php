<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateTrackingLocationRequest;
use App\Models\Offer;
use App\Services\BarterTrackingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BarterTrackingController extends BaseApiController
{
    public function __construct(
        protected BarterTrackingService $trackingService
    ) {}

    /**
     * Mengambil status pelacakan real-time untuk penawaran barter.
     */
    public function show(Request $request, int $offerId): JsonResponse
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            return $this->sendError('Transaksi barter tidak ditemukan.', [], 404);
        }

        try {
            $data = $this->trackingService->getTrackingStatus($request->user(), $offerId);

            return $this->sendResponse($data, 'Status pelacakan barter berhasil diambil.');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Memperbarui lokasi GPS pengguna dan status perjalanan.
     */
    public function updateLocation(UpdateTrackingLocationRequest $request, int $offerId): JsonResponse
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            return $this->sendError('Transaksi barter tidak ditemukan.', [], 404);
        }

        try {
            $tracking = $this->trackingService->updateLocation(
                $request->user(),
                $offerId,
                $request->validated()
            );

            return $this->sendResponse($tracking, 'Lokasi pelacakan berhasil diperbarui.');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Menghentikan pembagian lokasi pelacakan.
     */
    public function stop(Request $request, int $offerId): JsonResponse
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            return $this->sendError('Transaksi barter tidak ditemukan.', [], 404);
        }

        try {
            $tracking = $this->trackingService->stopTracking($request->user(), $offerId);

            return $this->sendResponse($tracking, 'Pembagian lokasi pelacakan berhasil dihentikan.');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
