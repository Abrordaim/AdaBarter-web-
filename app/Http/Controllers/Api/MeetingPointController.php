<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ProposeMeetingPointRequest;
use App\Models\MeetingPoint;
use App\Models\Offer;
use App\Services\MeetingPointService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MeetingPointController extends BaseApiController
{
    public function __construct(
        protected MeetingPointService $meetingPointService
    ) {}

    /**
     * Mengambil detail titik temu aktif dan usulan pending.
     */
    public function show(Request $request, int $offerId): JsonResponse
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            return $this->sendError('Transaksi barter tidak ditemukan.', [], 404);
        }

        try {
            Gate::authorize('view', [MeetingPoint::class, $offer]);

            $details = $this->meetingPointService->getMeetingPointDetails($request->user(), $offerId);

            return $this->sendResponse($details, 'Data titik temu barter berhasil diambil.');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Mengusulkan titik temu baru.
     */
    public function propose(ProposeMeetingPointRequest $request, int $offerId): JsonResponse
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            return $this->sendError('Transaksi barter tidak ditemukan.', [], 404);
        }

        try {
            Gate::authorize('propose', [MeetingPoint::class, $offer]);

            $meetingPoint = $this->meetingPointService->propose(
                $request->user(),
                $offerId,
                $request->validated()
            );

            return $this->sendResponse($meetingPoint, 'Usulan titik temu berhasil diajukan.', 201);
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (ValidationException $e) {
            return $this->sendError('Validasi gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Menyetujui usulan titik temu (menjadi locked).
     */
    public function agree(Request $request, int $offerId, int $meetingPointId): JsonResponse
    {
        $meetingPoint = MeetingPoint::where('id', $meetingPointId)->where('offer_id', $offerId)->first();
        if (!$meetingPoint) {
            return $this->sendError('Usulan titik temu tidak ditemukan pada transaksi ini.', [], 404);
        }

        try {
            Gate::authorize('agree', $meetingPoint);

            $lockedPoint = $this->meetingPointService->agree(
                $request->user(),
                $offerId,
                $meetingPointId
            );

            return $this->sendResponse($lockedPoint, 'Titik temu berhasil disepakati dan dikunci!');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Mengajukan usulan titik temu alternatif (counter proposal).
     */
    public function counter(ProposeMeetingPointRequest $request, int $offerId): JsonResponse
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            return $this->sendError('Transaksi barter tidak ditemukan.', [], 404);
        }

        try {
            Gate::authorize('propose', [MeetingPoint::class, $offer]);

            $meetingPoint = $this->meetingPointService->counter(
                $request->user(),
                $offerId,
                $request->validated()
            );

            return $this->sendResponse($meetingPoint, 'Usulan perubahan titik temu berhasil diajukan.', 201);
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (ValidationException $e) {
            return $this->sendError('Validasi gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
