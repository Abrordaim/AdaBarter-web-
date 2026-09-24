<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreItemRequest;
use App\Http\Requests\Api\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Services\ItemService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ItemController extends BaseApiController
{
    public function __construct(
        protected ItemService $itemService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id', 'city', 'condition']);
        $perPage = (int) $request->input('per_page', 15);

        $items = $this->itemService->getItems($filters, $perPage);

        return $this->sendResponse([
            'items' => ItemResource::collection($items),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Daftar barang berhasil diambil.');
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->itemService->getItemDetail($id);

        if (! $item) {
            return $this->sendError('Barang tidak ditemukan atau sudah tidak aktif.', [], 404);
        }

        return $this->sendResponse(new ItemResource($item), 'Detail barang berhasil diambil.');
    }

    public function myItems(Request $request): JsonResponse
    {
        $items = $this->itemService->getMyItems($request->user()->id);

        return $this->sendResponse(
            ItemResource::collection($items),
            'Daftar barang Anda berhasil diambil.'
        );
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        try {
            $images = $request->file('images', []);
            $item = $this->itemService->createItem($request->user(), $request->validated(), $images);

            return $this->sendResponse(
                new ItemResource($item),
                'Barang berhasil diunggah ke etalase barter!',
                201
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validasi gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengunggah barang: '.$e->getMessage(), [], 500);
        }
    }

    public function update(UpdateItemRequest $request, int $id): JsonResponse
    {
        try {
            $newImages = $request->file('new_images', []);
            $item = $this->itemService->updateItem($request->user(), $id, $request->validated(), $newImages);

            return $this->sendResponse(
                new ItemResource($item),
                'Informasi barang berhasil diperbarui.'
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal memperbarui barang: '.$e->getMessage(), [], 400);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->itemService->deleteItem($request->user(), $id);

            return $this->sendResponse(null, 'Barang berhasil dihapus.');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal menghapus barang: '.$e->getMessage(), [], 400);
        }
    }
}
