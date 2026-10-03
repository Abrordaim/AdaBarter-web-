<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Manajemen Kategori')]
class CategoryManagement extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $categoryId = null;

    public string $name = '';
    public string $slug = '';
    public string $icon = '';
    public string $description = '';
    public bool $is_active = true;
    public string $search = '';

    protected $rules = [
        'name'        => 'required|string|max:100',
        'slug'        => 'required|string|max:120',
        'icon'        => 'nullable|string|max:20',
        'description' => 'nullable|string|max:500',
        'is_active'   => 'boolean',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(string $value): void
    {
        if (! $this->categoryId) {
            $this->slug = Str::slug($value);
        }
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset(['categoryId', 'name', 'slug', 'icon', 'description']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $cat = Category::findOrFail($id);

        $this->categoryId  = $cat->id;
        $this->name        = $cat->name;
        $this->slug        = $cat->slug;
        $this->icon        = $cat->icon ?? '';
        $this->description = $cat->description ?? '';
        $this->is_active   = $cat->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $slugRule = $this->categoryId
            ? "required|string|max:120|unique:categories,slug,{$this->categoryId}"
            : 'required|string|max:120|unique:categories,slug';

        $data = $this->validate(array_merge($this->rules, ['slug' => $slugRule]));

        if ($this->categoryId) {
            Category::findOrFail($this->categoryId)->update($data);
            $this->dispatch('notify', message: 'Kategori berhasil diperbarui!');
        } else {
            Category::create($data);
            $this->dispatch('notify', message: 'Kategori berhasil ditambahkan!');
        }

        $this->showModal = false;
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $cat = Category::findOrFail($id);
        $cat->is_active = ! $cat->is_active;
        $cat->save();
        $this->dispatch('notify', message: 'Status kategori diperbarui.');
    }

    public function delete(int $id): void
    {
        $cat = Category::findOrFail($id);

        if ($cat->items()->count() > 0) {
            $this->dispatch('notify', message: 'Tidak dapat dihapus — kategori ini masih memiliki ' . $cat->items()->count() . ' barang terkait.');
            return;
        }

        $cat->delete();
        $this->dispatch('notify', message: 'Kategori berhasil dihapus.');
    }

    public function render()
    {
        $categories = Category::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('items')
            ->latest()
            ->paginate(10);

        return view('livewire.admin.category-management', compact('categories'));
    }
}
