<?php

namespace App\Repositories;

use App\Models\Banner;
use App\Repositories\Contracts\BannerRepositoryInterface;

class BannerRepository extends BaseRepository implements BannerRepositoryInterface
{
    public function __construct(Banner $model)
    {
        parent::__construct($model);
    }

    public function getActiveBanners(?string $position = null)
    {
        $query = $this->model->active()->notExpired();

        if ($position) {
            $query->where('position', $position);
        }

        return $query->orderBy('id', 'desc')->get();
    }
}
