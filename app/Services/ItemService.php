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

        $itemData = [
            'user_id' => $user->id,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'condition' => $data['condition'],
            'desired_items' => $data['desired_items'] ?? null,
            'estimated_price' => $data['estimated_price'] ?? null,
            'location' => $data['location'] ?? null,
            'city' => $data['city'] ?? $user->city,
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
        foreach (['category_id', 'title', 'description', 'condition', 'desired_items', 'estimated_price', 'location', 'city', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
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
