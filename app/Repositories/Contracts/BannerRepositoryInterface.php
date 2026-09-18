<?php

namespace App\Repositories\Contracts;

interface BannerRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveBanners(?string $position = null);
}
