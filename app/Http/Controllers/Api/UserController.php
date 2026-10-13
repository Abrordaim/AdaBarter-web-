<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function profile(Request $request): JsonResponse
    {
        $profile = $this->userService->getProfile($request->user());

        return $this->sendResponse($profile, 'Informasi profil dan kuota barter berhasil diambil.');
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $updatedUser = $this->userService->updateProfile(
                $request->user(),
                $request->validated(),
                $request->file('avatar')
            );

            return $this->sendResponse(
                new UserResource($updatedUser),
                'Profil berhasil diperbarui!'
            );
        } catch (\Exception $e) {
            return $this->sendError('Gagal memperbarui profil: ' . $e->getMessage(), [], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $user = \App\Models\User::find($id);

        if (! $user) {
            return $this->sendError('Pengguna tidak ditemukan.', [], 404);
        }

        $activeItems = $user->items()
            ->where('status', 'active')
            ->with(['category', 'user'])
            ->latest()
            ->get();

        $completedTradesCount = \App\Models\Offer::where(function ($q) use ($id) {
            $q->where('offerer_user_id', $id)
              ->orWhere('target_user_id', $id);
        })->where('status', 'completed')->count();

        return $this->sendResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'city' => $user->city,
                'avatar_url' => $user->avatar ? url(\Illuminate\Support\Facades\Storage::url($user->avatar)) : null,
                'is_vip' => $user->isVip(),
                'average_rating' => $user->averageRating(),
                'ratings_count' => $user->ratingsCount(),
                'completed_trades_count' => $completedTradesCount,
                'active_items_count' => $activeItems->count(),
                'created_at' => $user->created_at?->toISOString(),
            ],
            'items' => \App\Http\Resources\ItemResource::collection($activeItems),
        ], 'Profil pengguna berhasil diambil.');
    }
}
