<?php

namespace App\Services;

use App\Models\Item;
use App\Models\User;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemService
{
    public function __construct(
        protected ItemRepositoryInterface $itemRepository
    ) {}

    public function getItems(array $filters = [], int $perPage = 15)
    {
        return $this->itemRepository->getPaginated($filters, $perPage);
    }

    public function getItemDetail(int $id)
    {
        return $this->itemRepository->findActive($id);
    }

    public function getMyItems(int $userId)
    {
        return $this->itemRepository->getByUserId($userId);
    }

    /**
     * Create a new barter item.
     *
     * @param User $user
     * @param array $data
     * @param array<UploadedFile> $images
     * @return Item
     * @throws ValidationException
     */
    public function createItem(User $user, array $data, array $images = []): Item
    {
        // Enforce the 3-post quota validation
        if (!$user->canPost()) {
            throw ValidationException::withMessages([
                'quota' => ['Kuota posting aktif Anda telah mencapai batas maksimal (3 barang). Silakan klaim voucher kuota atau tingkatkan akun ke VIP untuk menambah slot postingan.'],
            ]);
        }

        $imagePaths = [];
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $path = $image->store('items', 'public');
                $imagePaths[] = $path;
            }
        }

        $provinceId = $data['province_id'] ?? $user->province_id;
        $regencyId = $data['regency_id'] ?? $user->regency_id;
        $districtId = $data['district_id'] ?? $user->district_id;

        $provinceName = $data['province_name'] ?? ($provinceId ? \App\Models\Province::find($provinceId)?->name : $user->province_name);
        $regencyName = $data['regency_name'] ?? ($regencyId ? \App\Models\Regency::find($regencyId)?->name : $user->regency_name);
        $districtName = $data['district_name'] ?? ($districtId ? \App\Models\District::find($districtId)?->name : $user->district_name);

        $itemData = [
            'user_id' => $user->id,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'condition' => $data['condition'],
            'desired_items' => $data['desired_items'] ?? null,
            'estimated_price' => $data['estimated_price'] ?? null,
            'location' => $data['location'] ?? null,
            'city' => $data['city'] ?? ($regencyName ?: $user->city),
            'province_id' => $provinceId,
            'regency_id' => $regencyId,
            'district_id' => $districtId,
            'province_name' => $provinceName,
            'regency_name' => $regencyName,
            'district_name' => $districtName,
            'status' => 'active',
            'is_boosted' => false,
            'images' => $imagePaths,
        ];

        return $this->itemRepository->create($itemData);
    }

    /**
     * Update an existing item.
     *
     * @param User $user
     * @param int $id
     * @param array $data
     * @param array<UploadedFile> $newImages
     * @return Item
     * @throws AuthorizationException
     */
    public function updateItem(User $user, int $id, array $data, array $newImages = []): Item
    {
        /** @var Item|null $item */
        $item = $this->itemRepository->find($id);

        if (!$item) {
            throw new \Exception('Barang tidak ditemukan.');
        }

        if ($item->user_id !== $user->id && !$user->isAdmin()) {
            throw new AuthorizationException('Anda tidak memiliki hak untuk mengubah barang ini.');
        }

        $updateData = [];
        $fields = [
            'category_id', 'title', 'description', 'condition', 'desired_items',
            'estimated_price', 'location', 'city', 'status',
            'province_id', 'regency_id', 'district_id',
            'province_name', 'regency_name', 'district_name',
        ];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // If regency_id changed but regency_name wasn't supplied
        if (isset($updateData['regency_id']) && empty($updateData['regency_name'])) {
            $reg = \App\Models\Regency::find($updateData['regency_id']);
            $updateData['regency_name'] = $reg?->name;
            if (empty($updateData['city']) && $reg) {
                $updateData['city'] = $reg->name;
            }
        }
        if (isset($updateData['province_id']) && empty($updateData['province_name'])) {
            $updateData['province_name'] = \App\Models\Province::find($updateData['province_id'])?->name;
        }
        if (isset($updateData['district_id']) && empty($updateData['district_name'])) {
            $updateData['district_name'] = \App\Models\District::find($updateData['district_id'])?->name;
        }

        if (!empty($newImages)) {
            $existingImages = $item->images ?? [];
            foreach ($newImages as $image) {
                if ($image instanceof UploadedFile) {
                    $existingImages[] = $image->store('items', 'public');
                }
            }
            $updateData['images'] = $existingImages;
        }

        return $this->itemRepository->update($id, $updateData);
    }

    /**
     * Delete an item.
     *
     * @param User $user
     * @param int $id
     * @return bool
     * @throws AuthorizationException
     */
    public function deleteItem(User $user, int $id): bool
    {
        /** @var Item|null $item */
        $item = $this->itemRepository->find($id);

        if (!$item) {
            throw new \Exception('Barang tidak ditemukan.');
        }

        if ($item->user_id !== $user->id && !$user->isAdmin()) {
            throw new AuthorizationException('Anda tidak memiliki hak untuk menghapus barang ini.');
        }

        return $this->itemRepository->delete($id);
    }
}
