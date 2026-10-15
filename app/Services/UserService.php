<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    public function countUser(string $role): int
    {
        return $this->userRepository->countByRole($role);
    }

    /**
     * Get user profile details with quota and counts.
     *
     * @param User $user
     * @return array
     */
    public function getProfile(User $user): array
    {
        $activeItemsCount = $user->items()->where('status', 'active')->count();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'city' => $user->city,
            'province_id' => $user->province_id,
            'province_name' => $user->province_name,
            'regency_id' => $user->regency_id,
            'regency_name' => $user->regency_name,
            'district_id' => $user->district_id,
            'district_name' => $user->district_name,
            'avatar' => $user->avatar ? url(Storage::url($user->avatar)) : null,
            'role' => $user->role,
            'is_vip' => $user->isVip(),
            'free_post_quota' => $user->free_post_quota,
            'bonus_post_quota' => $user->bonus_post_quota,
            'remaining_quota' => $user->remainingPostQuota(),
            'can_post' => $user->canPost(),
            'active_items_count' => $activeItemsCount,
            'total_items_count' => $user->items()->count(),
            'sent_offers_count' => $user->sentOffers()->count(),
            'received_offers_count' => $user->receivedOffers()->count(),
            'average_rating' => $user->averageRating(),
            'ratings_count' => $user->ratingsCount(),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }

    /**
     * Update user profile and optionally avatar.
     *
     * @param User $user
     * @param array $data
     * @param UploadedFile|null $avatar
     * @return User
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        if (isset($data['city'])) {
            $updateData['city'] = $data['city'];
        }

        if (array_key_exists('province_id', $data)) {
            $updateData['province_id'] = $data['province_id'];
            $updateData['province_name'] = $data['province_name'] ?? ($data['province_id'] ? \App\Models\Province::find($data['province_id'])?->name : null);
        }

        if (array_key_exists('regency_id', $data)) {
            $updateData['regency_id'] = $data['regency_id'];
            $regencyName = $data['regency_name'] ?? ($data['regency_id'] ? \App\Models\Regency::find($data['regency_id'])?->name : null);
            $updateData['regency_name'] = $regencyName;
            if (!isset($data['city']) && $regencyName) {
                $updateData['city'] = $regencyName;
            }
        }

        if (array_key_exists('district_id', $data)) {
            $updateData['district_id'] = $data['district_id'];
            $updateData['district_name'] = $data['district_name'] ?? ($data['district_id'] ? \App\Models\District::find($data['district_id'])?->name : null);
        }

        if ($avatar) {
            // Delete old avatar if stored locally
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $updateData['avatar'] = $avatar->store('avatars', 'public');
        }

        return $this->userRepository->update($user->id, $updateData);
    }
}