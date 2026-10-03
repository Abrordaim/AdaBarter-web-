<?php

use App\Livewire\Admin\ItemModeration;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->seller = User::factory()->create([
        'name' => 'Seller John',
        'email' => 'seller_john@adabarter.com',
    ]);

    $this->category = Category::create([
        'name' => 'Elektronik & Gadget',
        'slug' => 'elektronik-gadget',
        'is_active' => true,
    ]);
});

test('admin can access item moderation and management page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.items'))
        ->assertStatus(200)
        ->assertSee('Barang & Moderasi');
});

test('admin can create a new item for a user', function () {
    Livewire::actingAs($this->admin)
        ->test(ItemModeration::class)
        ->call('create')
        ->set('user_id', $this->seller->id)
        ->set('category_id', $this->category->id)
        ->set('title', 'Kamera DSLR Canon EOS')
        ->set('description', 'Kondisi mulus, jarang dipakai, lensa kit lengkap.')
        ->set('condition', 'bekas_seperti_baru')
        ->set('desired_items', 'Laptop atau iPad')
        ->set('estimated_price', 4500000)
        ->set('city', 'Jakarta Selatan')
        ->set('location', 'Kebayoran Baru')
        ->set('status', 'active')
        ->call('save')
        ->assertDispatched('notify');

    $this->assertDatabaseHas('items', [
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'title' => 'Kamera DSLR Canon EOS',
        'condition' => 'bekas_seperti_baru',
        'estimated_price' => 4500000,
        'status' => 'active',
        'city' => 'Jakarta Selatan',
    ]);
});

test('admin can edit an existing item', function () {
    $item = Item::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'title' => 'Judul Awal',
        'description' => 'Deskripsi awal yang panjang',
        'condition' => 'bekas_baik',
        'estimated_price' => 100000,
        'status' => 'moderated',
        'city' => 'Surabaya',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ItemModeration::class)
        ->call('edit', $item->id)
        ->set('title', 'Judul Telah Direvisi Admin')
        ->set('estimated_price', 250000)
        ->set('status', 'active')
        ->call('save')
        ->assertDispatched('notify');

    $item->refresh();
    expect($item->title)->toBe('Judul Telah Direvisi Admin');
    expect((float) $item->estimated_price)->toBe(250000.0);
    expect($item->status)->toBe('active');
});

test('admin can view item detail in modal', function () {
    $item = Item::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'title' => 'Barang Untuk Diinspeksi',
        'description' => 'Deskripsi barang inspeksi',
        'condition' => 'baru',
        'status' => 'active',
        'city' => 'Bandung',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ItemModeration::class)
        ->call('viewDetails', $item->id)
        ->assertSet('showDetailModal', true)
        ->assertSee('Barang Untuk Diinspeksi');
});

test('admin can approve and moderate items', function () {
    $item = Item::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'title' => 'Barang Uji Status',
        'description' => 'Deskripsi',
        'condition' => 'baru',
        'status' => 'moderated',
        'city' => 'Semarang',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ItemModeration::class)
        ->call('approveItem', $item->id)
        ->assertDispatched('notify');

    expect($item->fresh()->status)->toBe('active');

    Livewire::actingAs($this->admin)
        ->test(ItemModeration::class)
        ->call('moderateItem', $item->id)
        ->assertDispatched('notify');

    expect($item->fresh()->status)->toBe('moderated');
});

test('admin can delete item', function () {
    $item = Item::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'title' => 'Barang Dihapus',
        'description' => 'Deskripsi',
        'condition' => 'bekas_layak_pakai',
        'status' => 'active',
        'city' => 'Medan',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ItemModeration::class)
        ->call('deleteItem', $item->id)
        ->assertDispatched('notify');

    expect(Item::find($item->id))->toBeNull();
    expect(Item::withTrashed()->find($item->id))->not->toBeNull();
});
