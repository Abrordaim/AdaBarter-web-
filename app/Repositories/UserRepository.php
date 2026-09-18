<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email)
    {
        return $this->model->where('email', $email)->first();
    }

    public function countByRole(string $role): int
    {
        return $this->model->where('role', $role)->count();
    }

    public function addBonusQuota(int $userId, int $quota): bool
    {
        $user = $this->find($userId);
        if ($user) {
            return (bool) $user->increment('bonus_post_quota', $quota);
        }
        return false;
    }
}