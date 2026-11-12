<?php

namespace App\Http\Controllers\Api;

use App\Services\PenaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountHealthController extends BaseApiController
{
    public function __construct(
        protected PenaltyService $penaltyService
    ) {}

    /**
     * Get account health status and active penalties.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $data = $this->penaltyService->getAccountHealth($request->user());

            return $this->sendResponse($data, 'Data kesehatan akun berhasil diambil.');
        } catch (\Exception $e) {
            return $this->sendError('Gagal memuat data kesehatan akun: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Submit an appeal for a specific penalty point.
     */
    public function appeal(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'evidence' => 'nullable|file|image|max:5120',
        ], [
            'reason.required' => 'Alasan banding wajib diisi.',
            'reason.min' => 'Alasan banding minimal 10 karakter.',
            'reason.max' => 'Alasan banding maksimal 1000 karakter.',
            'evidence.image' => 'File bukti harus berupa gambar.',
            'evidence.max' => 'Ukuran file bukti maksimal 5MB.',
        ]);

        try {
            $penalty = $this->penaltyService->submitAppeal(
                $request->user(),
                $id,
                $validated['reason'],
                $request->file('evidence')
            );

            return $this->sendResponse([
                'id' => $penalty->id,
                'appeal_status' => $penalty->appeal_status,
                'appealed_at' => $penalty->appealed_at?->toISOString(),
            ], 'Permohonan banding Anda berhasil diajukan dan sedang dalam proses peninjauan oleh Tim Admin AdaBarter.');
        } catch (ValidationException $e) {
            return $this->sendError('Validasi gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengajukan banding: ' . $e->getMessage(), [], 500);
        }
    }
}
