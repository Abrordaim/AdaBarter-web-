<?php

namespace App\Repositories;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Repositories\Contracts\RegionRepositoryInterface;
use Illuminate\Support\Collection;

class RegionRepository implements RegionRepositoryInterface
{
    public function getAllProvinces(): Collection
    {
        return Province::select('id', 'code', 'name')
            ->orderBy('name')
            ->get();
    }

    public function getRegenciesByProvince(int $provinceId): Collection
    {
        return Regency::select('id', 'code', 'name', 'province_id')
            ->where('province_id', $provinceId)
            ->orderBy('name')
            ->get();
    }

    public function getDistrictsByRegency(int $regencyId): Collection
    {
        return District::select('id', 'code', 'name', 'regency_id')
            ->where('regency_id', $regencyId)
            ->orderBy('name')
            ->get();
    }
}
