<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Item;
use App\Models\Category;

#[Layout('components.layouts.admin')]
#[Title('Item Moderation')]
class ItemModeration extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $categoryFilter = '';

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingCategoryFilter() { $this->resetPage(); }

    public function approveItem($itemId)
    {
        $item = Item::findOrFail($itemId);
        $item->status = 'active';
        $item->save();
        $this->dispatch('notify', message: 'Item approved successfully.');
    }

    public function moderateItem($itemId)
    {
        $item = Item::findOrFail($itemId);
        $item->status = 'moderated';
        $item->save();
        $this->dispatch('notify', message: 'Item moved to moderated status.');
    }

    public function deleteItem($itemId)
    {
        $item = Item::findOrFail($itemId);
        $item->delete(); // Soft delete
        $this->dispatch('notify', message: 'Item deleted.');
    }

    public function render()
    {
        $query = Item::query()->with(['user', 'category']);

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        $items = $query->latest()->paginate(15);
        $categories = Category::all();

        return view('livewire.admin.item-moderation', [
            'items' => $items,
            'categories' => $categories,
        ]);
    }
}
