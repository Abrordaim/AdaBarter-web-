<?php

namespace App\Http\Controllers\Api;

use App\Services\RegionService;
use Illuminate\Http\JsonResponse;

class RegionController extends BaseApiController
{
    public function __construct(
        protected RegionService $regionService
    ) {}

    public function provinces(): JsonResponse
    {
        $provinces = $this->regionService->getProvinces();

        return $this->sendResponse($provinces, 'Daftar provinsi berhasil diambil.');
    }

    public function regencies(int $provinceId): JsonResponse
    {
        $regencies = $this->regionService->getRegencies($provinceId);

        return $this->sendResponse($regencies, 'Daftar kabupaten/kota berhasil diambil.');
    }

    public function districts(int $regencyId): JsonResponse
    {
        $districts = $this->regionService->getDistricts($regencyId);

        return $this->sendResponse($districts, 'Daftar kecamatan berhasil diambil.');
    }
}
