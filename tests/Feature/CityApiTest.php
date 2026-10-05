<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\User;

test('api returns list of available unique cities from active items', function () {
    $user1 = User::factory()->create(['city' => 'Jakarta Selatan']);
    $user2 = User::factory()->create(['city' => 'Surabaya']);
    $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);

    Item::create([
        'user_id' => $user1->id,
        'category_id' => $category->id,
        'title' => 'Barang di Bandung',
        'description' => 'Deskripsi',
        'condition' => 'baru',
        'status' => 'active',
        'city' => 'Bandung',
    ]);

    Item::create([
        'user_id' => $user2->id,
        'category_id' => $category->id,
        'title' => 'Barang di Surabaya',
        'description' => 'Deskripsi',
        'condition' => 'bekas_baik',
        'status' => 'active',
        'city' => 'Surabaya',
    ]);

    // Inactive item — should NOT appear in cities list
    Item::create([
        'user_id' => $user1->id,
        'category_id' => $category->id,
        'title' => 'Barang Tidak Aktif di Medan',
        'description' => 'Deskripsi',
        'condition' => 'baru',
        'status' => 'inactive',
        'city' => 'Medan',
    ]);

    $response = $this->getJson('/api/items/cities');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $cities = $response->json('data');
    expect($cities)->toContain('Bandung')
        ->toContain('Surabaya')
        ->not->toContain('Medan'); // inactive items excluded
});

test('cities list is sorted alphabetically', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Test', 'slug' => 'test']);

    foreach (['Yogyakarta', 'Bandung', 'Surabaya', 'Medan'] as $city) {
        Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => "Barang di {$city}",
            'description' => 'Deskripsi',
            'condition' => 'baru',
            'status' => 'active',
            'city' => $city,
        ]);
    }

    $response = $this->getJson('/api/items/cities');
    $response->assertStatus(200);
    $cities = $response->json('data');

    $sorted = collect($cities)->sort()->values()->toArray();
    expect($cities)->toBe($sorted);
});

test('cities list excludes null city values', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Misc', 'slug' => 'misc']);

    Item::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Barang tanpa kota',
        'description' => 'Deskripsi',
        'condition' => 'baru',
        'status' => 'active',
        'city' => null,
    ]);

    $response = $this->getJson('/api/items/cities');
    $response->assertStatus(200);

    $cities = $response->json('data');
    expect($cities)->not->toContain(null);
    expect($cities)->not->toContain('');
});
