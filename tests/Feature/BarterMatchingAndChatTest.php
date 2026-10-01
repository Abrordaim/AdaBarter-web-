<?php

use App\Models\Category;
use App\Models\Chat;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Elektronik & Gadget',
        'slug' => 'gadget',
        'icon' => 'smartphone',
    ]);

    $this->userA = User::factory()->create([
        'name' => 'Ahmad Penawar',
        'email' => 'ahmad@adabarter.com',
        'free_post_quota' => 3,
        'city' => 'Jakarta Selatan',
    ]);

    $this->userB = User::factory()->create([
        'name' => 'Bambang Pemilik Target',
        'email' => 'bambang@adabarter.com',
        'free_post_quota' => 3,
        'city' => 'Jakarta Timur',
    ]);

    $this->itemA = Item::create([
        'user_id' => $this->userA->id,
        'category_id' => $this->category->id,
        'title' => 'iPhone 11 128GB',
        'description' => 'Mulus pemakaian pribadi',
        'condition' => 'bekas_baik',
        'estimated_price' => 4500000,
        'city' => 'Jakarta Selatan',
        'status' => 'active',
    ]);

    $this->itemB = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'Samsung Galaxy S21 5G',
        'description' => 'Lengkap dus original',
        'condition' => 'bekas_seperti_baru',
        'estimated_price' => 5000000,
        'city' => 'Jakarta Timur',
        'status' => 'active',
    ]);

    $this->tokenA = $this->userA->createToken('testA')->plainTextToken;
    $this->tokenB = $this->userB->createToken('testB')->plainTextToken;
});

test('user can submit a barter proposal with cash supplement', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/offers', [
            'offerer_item_id' => $this->itemA->id,
            'target_item_id' => $this->itemB->id,
            'cash_supplement' => 500000,
            'cash_supplement_by' => 'offerer',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.cash_supplement', 500000)
        ->assertJsonPath('data.cash_supplement_by', 'offerer');

    $this->assertDatabaseHas('offers', [
        'offerer_user_id' => $this->userA->id,
        'target_user_id' => $this->userB->id,
        'offerer_item_id' => $this->itemA->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'pending',
        'cash_supplement' => 500000,
        'offerer_approved' => true,
        'target_approved' => false,
    ]);
});

test('user cannot propose barter to own item or duplicate pending offer', function () {
    // Cannot barter with self
    $selfOffer = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/offers', [
            'offerer_item_id' => $this->itemA->id,
            'target_item_id' => $this->itemA->id,
        ]);

    $selfOffer->assertStatus(422);

    // Initial valid offer
    $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/offers', [
            'offerer_item_id' => $this->itemA->id,
            'target_item_id' => $this->itemB->id,
        ])->assertStatus(201);

    // Duplicate pending offer should fail
    $duplicateOffer = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/offers', [
            'offerer_item_id' => $this->itemA->id,
            'target_item_id' => $this->itemB->id,
        ]);

    $duplicateOffer->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('target owner accepts offer, transitioning status to matched and initializing chat', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'target_user_id' => $this->userB->id,
        'offerer_item_id' => $this->itemA->id,
        'target_item_id' => $this->itemB->id,
        'cash_supplement' => 500000,
        'cash_supplement_by' => 'offerer',
        'status' => 'pending',
        'offerer_approved' => true,
        'target_approved' => false,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->postJson("/api/offers/{$offer->id}/accept");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'matched')
        ->assertJsonPath('data.target_approved', true);

    // Verify system chat initialized
    $this->assertDatabaseHas('chats', [
        'offer_id' => $offer->id,
        'type' => 'system',
    ]);
});

test('matched users can exchange chat messages and mark them read', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'target_user_id' => $this->userB->id,
        'offerer_item_id' => $this->itemA->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'matched',
        'offerer_approved' => true,
        'target_approved' => true,
        'matched_at' => now(),
    ]);

    // Offerer A sends message
    $sendA = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$offer->id}/chats", [
            'message' => 'Halo Mas Bambang, bisa COD besok sore di Mall Gandaria City?',
        ]);

    $sendA->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'Halo Mas Bambang, bisa COD besok sore di Mall Gandaria City?');

    // Flush auth cache before switching to user B
    auth()->forgetGuards();

    // Target B fetches messages (which marks A's message as read)
    $getMessagesB = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->getJson("/api/offers/{$offer->id}/chats");

    $getMessagesB->assertStatus(200)
        ->assertJsonPath('success', true);

    $chat = Chat::where('offer_id', $offer->id)
        ->where('sender_id', $this->userA->id)
        ->first();
    expect($chat->read_at)->not->toBeNull();

    // Flush auth cache before testing intruder
    auth()->forgetGuards();

    // Unauthorized user C cannot access chat room
    $userC = User::factory()->create(['email' => 'candra@adabarter.com']);
    $tokenC = $userC->createToken('testC')->plainTextToken;

    $intruderChat = $this->withHeader('Authorization', "Bearer {$tokenC}")
        ->getJson("/api/offers/{$offer->id}/chats");

    $intruderChat->assertStatus(403);
});

test('completing barter changes offer status to completed and marks both items traded', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'target_user_id' => $this->userB->id,
        'offerer_item_id' => $this->itemA->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'matched',
        'offerer_approved' => true,
        'target_approved' => true,
        'matched_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$offer->id}/complete");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'completed');

    expect($this->itemA->fresh()->status)->toBe('traded');
    expect($this->itemB->fresh()->status)->toBe('traded');

    $this->assertDatabaseHas('chats', [
        'offer_id' => $offer->id,
        'type' => 'system',
    ]);
});

test('target owner can reject pending offer', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'target_user_id' => $this->userB->id,
        'offerer_item_id' => $this->itemA->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'pending',
        'offerer_approved' => true,
        'target_approved' => false,
    ]);

    $rejectResponse = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->postJson("/api/offers/{$offer->id}/reject", [
            'reason' => 'Kurang cocok dengan tipe iPhone 11',
        ]);

    $rejectResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'rejected');

    expect($offer->fresh()->rejection_reason)->toBe('Kurang cocok dengan tipe iPhone 11');
});

test('offerer can cancel pending offer', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'target_user_id' => $this->userB->id,
        'offerer_item_id' => $this->itemA->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'pending',
        'offerer_approved' => true,
        'target_approved' => false,
    ]);

    $cancelResponse = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$offer->id}/cancel");

    $cancelResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'cancelled');

    expect($offer->fresh()->status)->toBe('cancelled');
});
