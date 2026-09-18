<?php

namespace App\Repositories\Contracts;

interface ItemRepositoryInterface extends BaseRepositoryInterface
{
    public function getPaginated(array $filters = [], int $perPage = 15);
    public function getByUserId(int $userId);
    public function findActive(int $id);
    public function countActiveByUserId(int $userId): int;
}
