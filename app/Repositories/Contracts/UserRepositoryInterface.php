<?php

namespace App\Repositories\Contracts;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email);
    public function countByRole(string $role): int;
    public function addBonusQuota(int $userId, int $quota): bool;
}
