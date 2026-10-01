<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'laptop',
    ]);
});

test('user can upload up to 3 items with free quota', function () {
    $user = User::factory()->create([
        'email' => 'seller@adabarter.com',
        'free_post_quota' => 3,
        'bonus_post_quota' => 0,
        'is_vip' => false,
        'city' => 'Jakarta Selatan',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    for ($i = 1; $i <= 3; $i++) {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/items', [
                'category_id' => $this->category->id,
                'title' => "Barang Barter #{$i}",
                'description' => "Deskripsi detail untuk barang barter #{$i}",
                'condition' => 'bekas_baik',
                'desired_items' => 'Gitar akustik atau smartphone',
                'estimated_price' => 500000,
                'location' => 'Tebet',
                'city' => 'Jakarta Selatan',
                'images' => [
                    UploadedFile::fake()->image("item_{$i}.jpg"),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', "Barang Barter #{$i}");
    }

    expect($user->items()->where('status', 'active')->count())->toBe(3);
    expect($user->remainingPostQuota())->toBe(0);
    expect($user->canPost())->toBeFalse();
});

test('uploading 4th item fails when quota of 3 is exhausted', function () {
    $user = User::factory()->create([
        'email' => 'maxed@adabarter.com',
        'free_post_quota' => 3,
        'bonus_post_quota' => 0,
        'is_vip' => false,
    ]);

    // Pre-create 3 active items
    for ($i = 1; $i <= 3; $i++) {
        Item::create([
            'user_id' => $user->id,
            'category_id' => $this->category->id,
            'title' => "Existing Item #{$i}",
            'description' => 'Sudah ada di etalase',
            'condition' => 'bekas_baik',
            'status' => 'active',
        ]);
    }

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/items', [
            'category_id' => $this->category->id,
            'title' => 'Barang Keempat (Harus Ditolak)',
            'description' => 'Mencoba upload melebihi batas kuota 3',
            'condition' => 'baru',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['quota']]);
});

test('claiming a voucher increases quota and permits uploading additional items', function () {
    $user = User::factory()->create([
        'email' => 'voucheruser@adabarter.com',
        'free_post_quota' => 3,
        'bonus_post_quota' => 0,
        'is_vip' => false,
    ]);

    // Fill 3 items
    for ($i = 1; $i <= 3; $i++) {
        Item::create([
            'user_id' => $user->id,
            'category_id' => $this->category->id,
            'title' => "Filled Item #{$i}",
            'description' => 'Batas awal',
            'condition' => 'bekas_baik',
            'status' => 'active',
        ]);
    }

    $voucher = Voucher::create([
        'code' => 'BONUS2POST',
        'description' => 'Bonus 2 Slot Posting',
        'quota_amount' => 2,
        'max_claims' => 100,
        'claimed_count' => 0,
        'is_active' => true,
        'expires_at' => now()->addDays(7),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // Claim voucher
    $claimResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/vouchers/claim', [
            'code' => 'BONUS2POST',
        ]);

    $claimResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.remaining_quota', 2);

    $user->refresh();
    expect($user->bonus_post_quota)->toBe(2);
    expect($user->canPost())->toBeTrue();

    // Now 4th item upload succeeds
    $fourthUpload = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/items', [
            'category_id' => $this->category->id,
            'title' => 'Barang Ke-4 Sukses Karena Voucher',
            'description' => 'Berhasil menggunakan bonus voucher',
            'condition' => 'baru',
        ]);

    $fourthUpload->assertStatus(201)
        ->assertJsonPath('success', true);
});

test('vip member has unlimited post quota and can post beyond 3 items', function () {
    $vipUser = User::factory()->create([
        'email' => 'vipmember@adabarter.com',
        'free_post_quota' => 3,
        'bonus_post_quota' => 0,
        'is_vip' => true,
    ]);

    // Pre-create 3 items
    for ($i = 1; $i <= 3; $i++) {
        Item::create([
            'user_id' => $vipUser->id,
            'category_id' => $this->category->id,
            'title' => "VIP Item #{$i}",
            'description' => 'Barang awal',
            'condition' => 'bekas_baik',
            'status' => 'active',
        ]);
    }

    expect($vipUser->canPost())->toBeTrue();

    $token = $vipUser->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/items', [
            'category_id' => $this->category->id,
            'title' => 'VIP 4th Item Without Limit',
            'description' => 'VIP bypasses remaining quota limit',
            'condition' => 'baru',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);
});

test('user cannot update or delete item belonging to another user', function () {
    $owner = User::factory()->create(['email' => 'owner@adabarter.com']);
    $other = User::factory()->create(['email' => 'intruder@adabarter.com']);

    $item = Item::create([
        'user_id' => $owner->id,
        'category_id' => $this->category->id,
        'title' => 'Barang Milik Owner',
        'description' => 'Original description',
        'condition' => 'bekas_baik',
        'status' => 'active',
    ]);

    $intruderToken = $other->createToken('test')->plainTextToken;

    // Intruder tries to update
    $updateResponse = $this->withHeader('Authorization', "Bearer {$intruderToken}")
        ->putJson("/api/items/{$item->id}", [
            'category_id' => $this->category->id,
            'title' => 'Hacked Title',
            'description' => 'Hacked description',
            'condition' => 'bekas_baik',
        ]);

    $updateResponse->assertStatus(403);

    // Intruder tries to delete
    $deleteResponse = $this->withHeader('Authorization', "Bearer {$intruderToken}")
        ->deleteJson("/api/items/{$item->id}");

    $deleteResponse->assertStatus(403);

    expect(Item::find($item->id)->title)->toBe('Barang Milik Owner');
});
