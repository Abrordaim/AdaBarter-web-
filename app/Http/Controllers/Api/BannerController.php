<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BannerResource;
use App\Services\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends BaseApiController
{
    public function __construct(
        protected BannerService $bannerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $position = $request->query('position');
        $banners = $this->bannerService->getActiveBanners($position);

        return $this->sendResponse(
            BannerResource::collection($banners),
            'Daftar banner iklan berhasil diambil.'
        );
    }
}
