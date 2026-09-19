<?php

namespace App\Services;

use App\Repositories\Contracts\BannerRepositoryInterface;

class BannerService
{
    public function __construct(
        protected BannerRepositoryInterface $bannerRepository
    ) {}

    public function getActiveBanners(?string $position = null)
    {
        return $this->bannerRepository->getActiveBanners($position);
    }
}
