<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends BaseApiController                     
{ 
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(): JsonResponse
    {
        $categories = $this->categoryService->getActiveCategories();

        return $this->sendResponse(
            CategoryResource::collection($categories),
            'Daftar kategori berhasil diambil.'
        );
    }
}
