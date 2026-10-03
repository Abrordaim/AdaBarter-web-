<?php

use App\Livewire\Admin\CategoryManagement;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);
});

test('admin can access categories page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.categories'))
        ->assertStatus(200)
        ->assertSee('Manajemen Kategori');
});

test('admin can create category', function () {
    Livewire::actingAs($this->admin)
        ->test(CategoryManagement::class)
        ->set('name', 'Peralatan Rumah')
        ->set('icon', '🏠')
        ->set('description', 'Perabot dan alat rumah tangga')
        ->call('save')
        ->assertDispatched('notify');

    $this->assertDatabaseHas('categories', [
        'name' => 'Peralatan Rumah',
        'slug' => 'peralatan-rumah',
        'icon' => '🏠',
    ]);
});

test('admin can edit category', function () {
    $category = Category::create([
        'name' => 'Buku Lama',
        'slug' => 'buku-lama',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(CategoryManagement::class)
        ->call('edit', $category->id)
        ->set('name', 'Buku & Komik')
        ->set('slug', 'buku-komik')
        ->call('save')
        ->assertDispatched('notify');

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Buku & Komik',
        'slug' => 'buku-komik',
    ]);
});

test('admin can toggle active status of category', function () {
    $category = Category::create([
        'name' => 'Elektronik Baru',
        'slug' => 'elektronik-baru',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(CategoryManagement::class)
        ->call('toggleActive', $category->id);

    expect($category->fresh()->is_active)->toBeFalse();
});

test('admin cannot delete category with items', function () {
    $category = Category::create([
        'name' => 'Gadget',
        'slug' => 'gadget',
        'is_active' => true,
    ]);

    Item::create([
        'user_id' => $this->admin->id,
        'category_id' => $category->id,
        'title' => 'Sample Item',
        'description' => 'Test item description',
        'condition' => 'bekas_baik',
        'desired_items' => 'Anything',
        'estimated_price' => 100000,
        'status' => 'active',
        'city' => 'Jakarta Selatan',
    ]);

    Livewire::actingAs($this->admin)
        ->test(CategoryManagement::class)
        ->call('delete', $category->id);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('admin can delete category without items', function () {
    $category = Category::create([
        'name' => 'Kategori Kosong',
        'slug' => 'kategori-kosong',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(CategoryManagement::class)
        ->call('delete', $category->id);

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});
