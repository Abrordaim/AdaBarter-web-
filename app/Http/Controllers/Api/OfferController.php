<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreOfferRequest;
use App\Http\Resources\OfferResource;
use App\Services\OfferService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException; 

class OfferController extends BaseApiController
{
    public function __construct(
        protected OfferService $offerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type'); // 'sent', 'received', or null (all)
        $status = $request->query('status'); // 'pending', 'matched', 'completed', etc.

        $offers = $this->offerService->getUserOffers($request->user(), $type, $status);

        return $this->sendResponse(
            OfferResource::collection($offers),
            'Daftar penawaran barter berhasil diambil.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $offer = $this->offerService->getOfferDetail($request->user(), $id);

            return $this->sendResponse(new OfferResource($offer), 'Detail penawaran berhasil diambil.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 404);
        }
    }

    public function store(StoreOfferRequest $request): JsonResponse
    {
        try {
            $offer = $this->offerService->createOffer($request->user(), $request->validated());

            return $this->sendResponse(
                new OfferResource($offer),
                'Penawaran barter berhasil dikirim ke pemilik barang!',
                201
            );
        } catch (ValidationException $e) {
            return $this->sendError('Pengajuan barter gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengajukan barter: ' . $e->getMessage(), [], 500);
        }
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        try {
            $offer = $this->offerService->acceptOffer($request->user(), $id);

            return $this->sendResponse(
                new OfferResource($offer),
                'Penawaran barter berhasil disetujui! Ruang chat negosiasi kini telah dibuka.'
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal menyetujui penawaran: ' . $e->getMessage(), [], 400);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $reason = $request->input('rejection_reason');
            $offer = $this->offerService->rejectOffer($request->user(), $id, $reason);

            return $this->sendResponse(
                new OfferResource($offer),
                'Penawaran barter berhasil ditolak.'
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal menolak penawaran: ' . $e->getMessage(), [], 400);
        }
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $offer = $this->offerService->completeOffer($request->user(), $id);

            return $this->sendResponse(
                new OfferResource($offer),
                'Transaksi barter telah berhasil diselesaikan secara langsung (COD).'
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal menyelesaikan barter: ' . $e->getMessage(), [], 400);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $offer = $this->offerService->cancelOffer($request->user(), $id);

            return $this->sendResponse(
                new OfferResource($offer),
                'Penawaran barter berhasil dibatalkan.'
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal membatalkan penawaran: ' . $e->getMessage(), [], 400);
        }
    }
}
