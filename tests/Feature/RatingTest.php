<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Offer;
use App\Models\Rating;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Elektronik & Gadget',
        'slug' => 'gadget',
        'icon' => 'smartphone',
    ]);

    $this->userA = User::factory()->create([
        'name' => 'User Penawar',
        'email' => 'userA@adabarter.com',
        'city' => 'Jakarta Selatan',
    ]);

    $this->userB = User::factory()->create([
        'name' => 'User Target',
        'email' => 'userB@adabarter.com',
        'city' => 'Surabaya',
    ]);

    $this->userC = User::factory()->create([
        'name' => 'User Luar',
        'email' => 'userC@adabarter.com',
    ]);

    $this->itemA = Item::create([
        'user_id' => $this->userA->id,
        'category_id' => $this->category->id,
        'title' => 'Barang A',
        'description' => 'Deskripsi A',
        'condition' => 'baru',
        'status' => 'traded',
    ]);

    $this->itemB = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'Barang B',
        'description' => 'Deskripsi B',
        'condition' => 'bekas_baik',
        'status' => 'traded',
    ]);

    $this->completedOffer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'offerer_item_id' => $this->itemA->id,
        'target_user_id' => $this->userB->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $this->pendingOffer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'offerer_item_id' => $this->itemA->id,
        'target_user_id' => $this->userB->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'pending',
    ]);

    $this->tokenA = $this->userA->createToken('testA')->plainTextToken;
    $this->tokenB = $this->userB->createToken('testB')->plainTextToken;
    $this->tokenC = $this->userC->createToken('testC')->plainTextToken;
});

test('user can submit rating after barter completed', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 5,
            'comment' => 'Barang sangat bagus dan ramah!',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.comment', 'Barang sangat bagus dan ramah!')
        ->assertJsonPath('data.rater.id', $this->userA->id);

    $this->assertDatabaseHas('ratings', [
        'offer_id' => $this->completedOffer->id,
        'rater_id' => $this->userA->id,
        'rated_user_id' => $this->userB->id,
        'rating' => 5,
    ]);
});

test('both participants can rate each other for a completed barter', function () {
    // User A rates User B
    $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 5,
            'comment' => 'Mantap sekali',
        ])
        ->assertStatus(201);

    // Flush auth cache before User B request
    auth()->forgetGuards();

    // User B rates User A
    $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 4,
            'comment' => 'Transaksi lancar dan cepat',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.rating', 4);

    expect(Rating::where('offer_id', $this->completedOffer->id)->count())->toBe(2);
});

test('user cannot rate when barter is not completed', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->pendingOffer->id,
            'rating' => 5,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('user cannot rate twice for the same offer', function () {
    // First rating
    $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 5,
        ])
        ->assertStatus(201);

    // Second rating by same user on same offer
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 3,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('non participant cannot rate an offer', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenC}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 5,
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('rating requires valid stars between 1 and 5', function () {
    // 0 stars - invalid
    $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 0,
        ])
        ->assertStatus(422);

    // 6 stars - invalid
    $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/ratings', [
            'offer_id' => $this->completedOffer->id,
            'rating' => 6,
        ])
        ->assertStatus(422);
});

test('user average rating and ratings count are calculated correctly in UserResource', function () {
    // Give user B two ratings: 5 and 4 -> average 4.5
    Rating::create([
        'offer_id' => $this->completedOffer->id,
        'rater_id' => $this->userA->id,
        'rated_user_id' => $this->userB->id,
        'rating' => 5,
        'comment' => 'Bintang 5',
    ]);

    // Create another completed offer for second rating
    $offer2 = Offer::create([
        'offerer_user_id' => $this->userC->id,
        'offerer_item_id' => $this->itemA->id,
        'target_user_id' => $this->userB->id,
        'target_item_id' => $this->itemB->id,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    Rating::create([
        'offer_id' => $offer2->id,
        'rater_id' => $this->userC->id,
        'rated_user_id' => $this->userB->id,
        'rating' => 4,
        'comment' => 'Bintang 4',
    ]);

    expect($this->userB->averageRating())->toBe(4.5);
    expect($this->userB->ratingsCount())->toBe(2);

    // Check in GET /api/user/profile for userB
    $res = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->getJson('/api/user/profile');

    $res->assertStatus(200)
        ->assertJsonPath('data.average_rating', 4.5)
        ->assertJsonPath('data.ratings_count', 2);
});

test('public endpoint GET /api/users/{id}/ratings returns list of ratings', function () {
    Rating::create([
        'offer_id' => $this->completedOffer->id,
        'rater_id' => $this->userA->id,
        'rated_user_id' => $this->userB->id,
        'rating' => 5,
        'comment' => 'Ulasan publik untuk user B',
    ]);

    $response = $this->getJson("/api/users/{$this->userB->id}/ratings");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.name', 'User Target')
        ->assertJsonPath('data.user.average_rating', 5)
        ->assertJsonPath('data.user.ratings_count', 1)
        ->assertJsonPath('data.ratings.0.rating', 5)
        ->assertJsonPath('data.ratings.0.comment', 'Ulasan publik untuk user B')
        ->assertJsonPath('data.ratings.0.rater.name', 'User Penawar');
});
