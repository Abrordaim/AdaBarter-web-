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