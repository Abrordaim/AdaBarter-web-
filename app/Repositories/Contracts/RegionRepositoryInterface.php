<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface RegionRepositoryInterface
{
    /** @return Collection<int, \App\Models\Province> */
    public function getAllProvinces(): Collection;

    /** @return Collection<int, \App\Models\Regency> */
    public function getRegenciesByProvince(int $provinceId): Collection;

    /** @return Collection<int, \App\Models\District> */
    public function getDistrictsByRegency(int $regencyId): Collection;
}
