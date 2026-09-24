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
}
