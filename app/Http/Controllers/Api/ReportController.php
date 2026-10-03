<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreReportRequest;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ReportController extends BaseApiController
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $type = $request->input('reportable_type');
            $id = $request->input('reportable_id');

            $modelClass = match ($type) {
                'item' => Item::class,
                'user' => User::class,
                default => null,
            };

            if (! $modelClass) {
                return $this->sendError('Tipe objek yang dilaporkan tidak valid.', [], 422);
            }

            $target = $modelClass::find($id);
            if (! $target) {
                return $this->sendError('Objek yang dilaporkan tidak ditemukan.', [], 404);
            }

            // Determine reported_user_id
            $reportedUserId = null;
            if ($type === 'item') {
                $reportedUserId = $target->user_id;
                if ($reportedUserId === $user->id) {
                    return $this->sendError('Anda tidak dapat melaporkan barang milik sendiri.', [], 422);
                }
            } elseif ($type === 'user') {
                $reportedUserId = $target->id;
                if ($reportedUserId === $user->id) {
                    return $this->sendError('Anda tidak dapat melaporkan akun sendiri.', [], 422);
                }
            }

            $evidencePath = null;
            if ($request->hasFile('evidence')) {
                $evidencePath = $request->file('evidence')->store('reports', 'public');
            }

            $report = Report::create([
                'reporter_id' => $user->id,
                'reported_user_id' => $reportedUserId,
                'reportable_type' => $modelClass,
                'reportable_id' => $id,
                'reason' => $request->input('reason'),
                'description' => $request->input('description'),
                'evidence_image' => $evidencePath,
                'status' => 'pending',
            ]);

            return $this->sendResponse([
                'id' => $report->id,
                'status' => $report->status,
                'created_at' => $report->created_at->toISOString(),
            ], 'Laporan Anda telah berhasil dikirim ke Admin. Terima kasih telah menjaga keamanan komunitas AdaBarter.', 201);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengirim laporan: ' . $e->getMessage(), [], 500);
        }
    }
}
