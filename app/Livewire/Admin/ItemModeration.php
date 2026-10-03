<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Barang & Moderasi')]
class ItemModeration extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';

    // Modals state
    public bool $showModal = false;
    public bool $showDetailModal = false;
    public ?int $itemId = null;
    public ?Item $selectedItem = null;

    // Form fields
    public ?int $user_id = null;
    public ?int $category_id = null;
    public string $title = '';
    public string $description = '';
    public string $condition = 'bekas_baik';
    public string $desired_items = '';
    public ?float $estimated_price = null;
    public string $location = '';
    public string $city = '';
    public string $status = 'active';

    public array $existing_images = [];
    public $new_images = [];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingCategoryFilter(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset([
            'itemId', 'user_id', 'category_id', 'title', 'description',
            'desired_items', 'estimated_price', 'location', 'city',
            'existing_images', 'new_images',
        ]);
        $this->condition = 'bekas_baik';
        $this->status = 'active';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $item = Item::findOrFail($id);

        $this->itemId          = $item->id;
        $this->user_id         = $item->user_id;
        $this->category_id     = $item->category_id;
        $this->title           = $item->title;
        $this->description     = $item->description;
        $this->condition       = $item->condition;
        $this->desired_items   = $item->desired_items ?? '';
        $this->estimated_price = $item->estimated_price ? (float) $item->estimated_price : null;
        $this->location        = $item->location ?? '';
        $this->city            = $item->city ?? '';
        $this->status          = $item->status;

        $rawImages = is_string($item->images) ? json_decode($item->images, true) : $item->images;
        $this->existing_images = is_array($rawImages) ? $rawImages : [];
        $this->new_images      = [];

        $this->showModal = true;
    }

    public function viewDetails(int $id): void
    {
        $this->selectedItem = Item::with(['user', 'category', 'offersAsTarget.offererItem', 'offersAsTarget.user'])->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function removeExistingImage(int $index): void
    {
        if (isset($this->existing_images[$index])) {
            array_splice($this->existing_images, $index, 1);
        }
    }

    public function removeNewImage(int $index): void
    {
        if (isset($this->new_images[$index])) {
            array_splice($this->new_images, $index, 1);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'user_id'         => 'required|exists:users,id',
            'category_id'     => 'required|exists:categories,id',
            'title'           => 'required|string|max:255',
            'description'     => 'required|string',
            'condition'       => 'required|in:baru,bekas_seperti_baru,bekas_baik,bekas_layak_pakai',
            'desired_items'   => 'nullable|string|max:255',
            'estimated_price' => 'nullable|numeric|min:0',
            'location'        => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:100',
            'status'          => 'required|in:active,inactive,moderated,traded',
            'new_images.*'    => 'nullable|image|max:3072',
        ]);

        // Upload new images
        $uploadedPaths = [];
        if (! empty($this->new_images)) {
            foreach ($this->new_images as $image) {
                if ($image) {
                    $uploadedPaths[] = $image->store('items', 'public');
                }
            }
        }

        $allImages = array_values(array_merge($this->existing_images, $uploadedPaths));

        $data = [
            'user_id'         => $validated['user_id'],
            'category_id'     => $validated['category_id'],
            'title'           => $validated['title'],
            'description'     => $validated['description'],
            'condition'       => $validated['condition'],
            'desired_items'   => $validated['desired_items'] ?: null,
            'estimated_price' => $validated['estimated_price'] !== null ? $validated['estimated_price'] : null,
            'location'        => $validated['location'] ?: null,
            'city'            => $validated['city'] ?: null,
            'status'          => $validated['status'],
            'images'          => $allImages,
        ];

        if ($this->itemId) {
            $item = Item::findOrFail($this->itemId);
            $item->update($data);
            $this->dispatch('notify', message: 'Data barang berhasil diperbarui.');
        } else {
            Item::create($data);
            $this->dispatch('notify', message: 'Barang baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetPage();
    }

    public function approveItem(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        $item->status = 'active';
        $item->save();
        $this->dispatch('notify', message: 'Barang berhasil disetujui (Active).');
    }

    public function moderateItem(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        $item->status = 'moderated';
        $item->save();
        $this->dispatch('notify', message: 'Barang diubah ke status Moderated.');
    }

    public function deleteItem(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        $item->delete(); // Soft delete
        $this->dispatch('notify', message: 'Barang berhasil dihapus.');
    }

    public function render()
    {
        $query = Item::query()->with(['user', 'category']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhere('city', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        $items = $query->latest()->paginate(15);
        $categories = Category::all();
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();

        return view('livewire.admin.item-moderation', [
            'items'      => $items,
            'categories' => $categories,
            'users'      => $users,
        ]);
    }
}
