<?php

namespace App\Services;

use App\Repositories\Contracts\RegionRepositoryInterface;
use Illuminate\Support\Collection;

class RegionService
{
    public function __construct(
        protected RegionRepositoryInterface $regionRepository
    ) {}

    public function getProvinces(): Collection
    {
        return $this->regionRepository->getAllProvinces();
    }

    public function getRegencies(int $provinceId): Collection
    {
        return $this->regionRepository->getRegenciesByProvince($provinceId);
    }

    public function getDistricts(int $regencyId): Collection
    {
        return $this->regionRepository->getDistrictsByRegency($regencyId);
    }
}
