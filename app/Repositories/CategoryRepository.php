<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function getActiveCategories()
    {
        return $this->model->where('is_active', true)
            ->withCount(['items' => function ($q) {
                $q->where('status', 'active');
            }])
            ->orderBy('name')
            ->get();
    }
}
