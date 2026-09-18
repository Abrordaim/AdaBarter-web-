<?php

namespace App\Repositories;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;

class ItemRepository extends BaseRepository implements ItemRepositoryInterface
{
    public function __construct(Item $model)
    {
        parent::__construct($model);
    }

    public function getPaginated(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->with(['user:id,name,city,avatar,is_vip', 'category:id,name,slug,icon'])
            ->where('status', 'active');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('desired_items', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (!empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Boosted items first, then newest
        return $query->orderByDesc('is_boosted')
                     ->orderByDesc('created_at')
                     ->paginate($perPage);
    }

    public function getByUserId(int $userId)
    {
        return $this->model->with(['category:id,name,slug,icon'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findActive(int $id)
    {
        return $this->model->with(['user:id,name,city,avatar,is_vip', 'category:id,name,slug,icon'])
            ->where('status', 'active')
            ->find($id);
    }

    public function countActiveByUserId(int $userId): int
    {
        return $this->model->where('user_id', $userId)
            ->where('status', 'active')
            ->count();
    }
}
