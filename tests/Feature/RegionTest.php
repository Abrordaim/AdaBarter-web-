<?php

use App\Models\Category;
use App\Models\District;
use App\Models\Item;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    // Create test region hierarchy
    $this->provinceJabar = Province::create([
        'id' => 32,
        'code' => '32',
        'name' => 'JAWA BARAT',
    ]);

    $this->provinceJateng = Province::create([
        'id' => 33,
        'code' => '33',
        'name' => 'JAWA TENGAH',
    ]);

    $this->regencyBandung = Regency::create([
        'id' => 3273,
        'province_id' => 32,
        'code' => '32.73',
        'name' => 'KOTA BANDUNG',
    ]);

    $this->regencyBogor = Regency::create([
        'id' => 3271,
        'province_id' => 32,
        'code' => '32.71',
        'name' => 'KOTA BOGOR',
    ]);

    $this->regencySemarang = Regency::create([
        'id' => 3374,
        'province_id' => 33,
        'code' => '33.74',
        'name' => 'KOTA SEMARANG',
    ]);

    $this->districtCoblong = District::create([
        'id' => 3273010,
        'regency_id' => 3273,
        'code' => '32.73.01',
        'name' => 'Coblong',
    ]);

    $this->districtSukajadi = District::create([
        'id' => 3273020,
        'regency_id' => 3273,
        'code' => '32.73.02',
        'name' => 'Sukajadi',
    ]);

    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'laptop',
    ]);
});

test('can get all provinces via api', function () {
    $response = $this->getJson('/api/regions/provinces');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect(count($data))->toBe(2);
    expect($data[0]['name'])->toBe('JAWA BARAT');
    expect($data[1]['name'])->toBe('JAWA TENGAH');
});

test('can get regencies of a specific province', function () {
    $response = $this->getJson('/api/regions/provinces/32/regencies');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect(count($data))->toBe(2);
    $names = collect($data)->pluck('name')->toArray();
    expect($names)->toContain('KOTA BANDUNG')
        ->toContain('KOTA BOGOR')
        ->not->toContain('KOTA SEMARANG');
});

test('can get districts of a specific regency', function () {
    $response = $this->getJson('/api/regions/regencies/3273/districts');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect(count($data))->toBe(2);
    $names = collect($data)->pluck('name')->toArray();
    expect($names)->toContain('Coblong')
        ->toContain('Sukajadi');
});

test('user can register with full region hierarchy', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Ahmad Bandung',
        'email' => 'ahmad@adabarter.com',
        'password' => 'secret123',
        'phone' => '08123456789',
        'province_id' => 32,
        'province_name' => 'JAWA BARAT',
        'regency_id' => 3273,
        'regency_name' => 'KOTA BANDUNG',
        'district_id' => 3273010,
        'district_name' => 'Coblong',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.province_id', 32)
        ->assertJsonPath('data.user.regency_id', 3273)
        ->assertJsonPath('data.user.district_id', 3273010)
        ->assertJsonPath('data.user.province_name', 'JAWA BARAT')
        ->assertJsonPath('data.user.regency_name', 'KOTA BANDUNG')
        ->assertJsonPath('data.user.district_name', 'Coblong');

    $this->assertDatabaseHas('users', [
        'email' => 'ahmad@adabarter.com',
        'province_id' => 32,
        'regency_id' => 3273,
        'district_id' => 3273010,
        'province_name' => 'JAWA BARAT',
        'regency_name' => 'KOTA BANDUNG',
        'district_name' => 'Coblong',
        'city' => 'KOTA BANDUNG', // Auto-filled from regency_name for backward compatibility
    ]);
});

test('user can update profile with region fields', function () {
    $user = User::factory()->create([
        'city' => 'Old City',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/user/profile', [
            'name' => 'Updated Name',
            'phone' => '08987654321',
            'province_id' => 32,
            'province_name' => 'JAWA BARAT',
            'regency_id' => 3271,
            'regency_name' => 'KOTA BOGOR',
            'district_id' => null,
            'district_name' => null,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.province_id', 32)
        ->assertJsonPath('data.regency_id', 3271)
        ->assertJsonPath('data.regency_name', 'KOTA BOGOR');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'regency_id' => 3271,
        'regency_name' => 'KOTA BOGOR',
        'city' => 'KOTA BOGOR',
    ]);
});

test('item creation auto-inherits region from user profile when not provided', function () {
    $user = User::factory()->create([
        'free_post_quota' => 3,
        'province_id' => 32,
        'province_name' => 'JAWA BARAT',
        'regency_id' => 3273,
        'regency_name' => 'KOTA BANDUNG',
        'district_id' => 3273010,
        'district_name' => 'Coblong',
        'city' => 'KOTA BANDUNG',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/items', [
            'category_id' => $this->category->id,
            'title' => 'Barang Otomatis Lokasi User',
            'description' => 'Deskripsi barang warisan user',
            'condition' => 'baru',
            'images' => [
                UploadedFile::fake()->image('item1.jpg'),
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.province_id', 32)
        ->assertJsonPath('data.regency_id', 3273)
        ->assertJsonPath('data.district_id', 3273010)
        ->assertJsonPath('data.province_name', 'JAWA BARAT')
        ->assertJsonPath('data.regency_name', 'KOTA BANDUNG')
        ->assertJsonPath('data.district_name', 'Coblong');

    $this->assertDatabaseHas('items', [
        'title' => 'Barang Otomatis Lokasi User',
        'province_id' => 32,
        'regency_id' => 3273,
        'district_id' => 3273010,
        'city' => 'KOTA BANDUNG',
    ]);
});

test('item can be created with explicit custom region overriding user profile', function () {
    $user = User::factory()->create([
        'free_post_quota' => 3,
        'province_id' => 32,
        'province_name' => 'JAWA BARAT',
        'regency_id' => 3273,
        'regency_name' => 'KOTA BANDUNG',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/items', [
            'category_id' => $this->category->id,
            'title' => 'Barang di Semarang',
            'description' => 'Disimpan di rumah orang tua di Semarang',
            'condition' => 'baru',
            'province_id' => 33,
            'province_name' => 'JAWA TENGAH',
            'regency_id' => 3374,
            'regency_name' => 'KOTA SEMARANG',
            'images' => [
                UploadedFile::fake()->image('item2.jpg'),
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.province_id', 33)
        ->assertJsonPath('data.regency_id', 3374)
        ->assertJsonPath('data.regency_name', 'KOTA SEMARANG');

    $this->assertDatabaseHas('items', [
        'title' => 'Barang di Semarang',
        'province_id' => 33,
        'regency_id' => 3374,
    ]);
});

test('can filter items by regency_id and province_id', function () {
    $user = User::factory()->create();

    // Item 1 in Bandung (Jabar)
    Item::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Sepeda Bandung',
        'description' => 'Deskripsi',
        'condition' => 'baru',
        'status' => 'active',
        'province_id' => 32,
        'province_name' => 'JAWA BARAT',
        'regency_id' => 3273,
        'regency_name' => 'KOTA BANDUNG',
        'city' => 'KOTA BANDUNG',
    ]);

    // Item 2 in Bogor (Jabar)
    Item::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Kamera Bogor',
        'description' => 'Deskripsi',
        'condition' => 'bekas_baik',
        'status' => 'active',
        'province_id' => 32,
        'province_name' => 'JAWA BARAT',
        'regency_id' => 3271,
        'regency_name' => 'KOTA BOGOR',
        'city' => 'KOTA BOGOR',
    ]);

    // Item 3 in Semarang (Jateng)
    Item::create([
        'user_id' => $user->id,
        'category_id' => $this->category->id,
        'title' => 'Gitar Semarang',
        'description' => 'Deskripsi',
        'condition' => 'baru',
        'status' => 'active',
        'province_id' => 33,
        'province_name' => 'JAWA TENGAH',
        'regency_id' => 3374,
        'regency_name' => 'KOTA SEMARANG',
        'city' => 'KOTA SEMARANG',
    ]);

    // Filter by regency: Bandung only
    $resRegency = $this->getJson('/api/items?regency_id=3273');
    $resRegency->assertStatus(200);
    $itemsBandung = $resRegency->json('data.items');
    expect(count($itemsBandung))->toBe(1);
    expect($itemsBandung[0]['title'])->toBe('Sepeda Bandung');

    // Filter by province: Jawa Barat (Bandung + Bogor)
    $resProvince = $this->getJson('/api/items?province_id=32');
    $resProvince->assertStatus(200);
    $itemsJabar = $resProvince->json('data.items');
    expect(count($itemsJabar))->toBe(2);
    $titles = collect($itemsJabar)->pluck('title')->toArray();
    expect($titles)->toContain('Sepeda Bandung')->toContain('Kamera Bogor');
});
