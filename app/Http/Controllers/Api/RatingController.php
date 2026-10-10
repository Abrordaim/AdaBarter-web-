<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Offer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RatingController extends BaseApiController
{
    /**
     * Submit a rating and review for a completed barter transaction.
     */
    public function store(StoreRatingRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $offer = Offer::find($request->validated('offer_id'));

            if (! $offer) {
                return $this->sendError('Transaksi barter tidak ditemukan.', [], 404);
            }

            if ($offer->status !== 'completed') {
                return $this->sendError('Penilaian hanya dapat diberikan setelah transaksi barter selesai.', [], 422);
            }

            // Check if user is a participant of this offer
            if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id) {
                return $this->sendError('Anda bukan peserta dari transaksi barter ini.', [], 403);
            }

            // Determine who is being rated (the other participant)
            $ratedUserId = $offer->offerer_user_id === $user->id
                ? $offer->target_user_id
                : $offer->offerer_user_id;

            // Check if already rated
            $existing = Rating::where('offer_id', $offer->id)
                ->where('rater_id', $user->id)
                ->exists();

            if ($existing) {
                return $this->sendError('Anda sudah memberikan penilaian untuk transaksi barter ini.', [], 422);
            }

            $rating = Rating::create([
                'offer_id' => $offer->id,
                'rater_id' => $user->id,
                'rated_user_id' => $ratedUserId,
                'rating' => $request->validated('rating'),
                'comment' => $request->validated('comment'),
            ]);

            $rating->load(['rater', 'ratedUser']);

            return $this->sendResponse(
                new RatingResource($rating),
                'Terima kasih! Penilaian barter Anda berhasil dikirim.',
                201
            );
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengirim penilaian: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get public ratings & reviews received by a specific user.
     */
    public function userRatings(Request $request, int $userId): JsonResponse
    {
        $targetUser = User::find($userId);

        if (! $targetUser) {
            return $this->sendError('Pengguna tidak ditemukan.', [], 404);
        }

        $perPage = (int) $request->input('per_page', 10);
        $ratings = Rating::with('rater')
            ->where('rated_user_id', $userId)
            ->latest()
            ->paginate($perPage);

        return $this->sendResponse([
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'avatar_url' => $targetUser->avatar ? url(Storage::url($targetUser->avatar)) : null,
                'city' => $targetUser->city,
                'average_rating' => $targetUser->averageRating(),
                'ratings_count' => $targetUser->ratingsCount(),
            ],
            'ratings' => RatingResource::collection($ratings),
            'pagination' => [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
            ],
        ], 'Daftar ulasan pengguna berhasil diambil.');
    }
}
