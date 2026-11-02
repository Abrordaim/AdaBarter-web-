<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\MeetingPoint;
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
        'city' => 'Jakarta Selatan',
    ]);

    $this->userB = User::factory()->create([
        'name' => 'Bambang Pemilik Target',
        'email' => 'bambang@adabarter.com',
        'city' => 'Jakarta Timur',
    ]);

    $this->userC = User::factory()->create([
        'name' => 'Charlie Orang Ketiga',
        'email' => 'charlie@adabarter.com',
        'city' => 'Bandung',
    ]);

    $this->itemA = Item::create([
        'user_id' => $this->userA->id,
        'category_id' => $this->category->id,
        'title' => 'iPhone 11 128GB',
        'description' => 'Kondisi mulus terawat',
        'condition' => 'bekas_baik',
        'estimated_price' => 4500000,
        'city' => 'Jakarta Selatan',
        'status' => 'active',
    ]);

    $this->itemB = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'Samsung S21',
        'description' => 'Kondisi lengkap dus original',
        'condition' => 'bekas_seperti_baru',
        'estimated_price' => 5000000,
        'city' => 'Jakarta Timur',
        'status' => 'active',
    ]);

    $this->tokenA = $this->userA->createToken('testA')->plainTextToken;
    $this->tokenB = $this->userB->createToken('testB')->plainTextToken;
    $this->tokenC = $this->userC->createToken('testC')->plainTextToken;

    // Default matched offer
    $this->offer = Offer::create([
        'offerer_user_id'  => $this->userA->id,
        'offerer_item_id'  => $this->itemA->id,
        'target_user_id'   => $this->userB->id,
        'target_item_id'   => $this->itemB->id,
        'payment_method'   => 'cod',
        'price_difference' => 500000,
        'payer_id'         => $this->userA->id,
        'receiver_id'      => $this->userB->id,
        'status'           => 'matched',
        'offerer_approved' => true,
        'target_approved'  => true,
        'matched_at'       => now(),
    ]);
});

test('non-participant cannot view or propose meeting point (IDOR protection)', function () {
    // Charlie tries to view meeting point details
    $responseView = $this->withHeader('Authorization', "Bearer {$this->tokenC}")
        ->getJson("/api/offers/{$this->offer->id}/meeting-point");

    $responseView->assertStatus(403);

    // Charlie tries to propose meeting point
    $responsePropose = $this->withHeader('Authorization', "Bearer {$this->tokenC}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/propose", [
            'latitude'   => -6.1753924,
            'longitude'  => 106.8271528,
            'place_name' => 'Monas Jakarta',
        ]);

    $responsePropose->assertStatus(403);
});

test('participant cannot propose meeting point if barter offer is not matched', function () {
    // Set offer status to pending
    $this->offer->update(['status' => 'pending']);

    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/propose", [
            'latitude'   => -6.1753924,
            'longitude'  => 106.8271528,
            'place_name' => 'Monas Jakarta',
        ]);

    $response->assertStatus(403);
});

test('participant can propose meeting point with valid coordinates', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/propose", [
            'latitude'   => -6.1950,
            'longitude'  => 106.8230,
            'place_name' => 'Grand Indonesia - West Mall Lobby',
            'address'    => 'Jl. M.H. Thamrin No.1, Jakarta Pusat',
            'notes'      => 'Di depan gerai Starbucks',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.place_name', 'Grand Indonesia - West Mall Lobby')
        ->assertJsonPath('data.status', 'proposed')
        ->assertJsonPath('data.proposed_by', $this->userA->id);

    $this->assertDatabaseHas('meeting_points', [
        'offer_id'    => $this->offer->id,
        'proposed_by' => $this->userA->id,
        'place_name'  => 'Grand Indonesia - West Mall Lobby',
        'status'      => 'proposed',
    ]);

    // Check system chat message created
    $this->assertDatabaseHas('chats', [
        'offer_id'  => $this->offer->id,
        'sender_id' => $this->userA->id,
        'type'      => 'system',
    ]);
});

test('invalid coordinates return validation error', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/propose", [
            'latitude'   => 999.0, // Invalid latitude
            'longitude'  => 106.8230,
            'place_name' => 'Grand Indonesia',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);
});

test('proposer cannot agree to their own proposal', function () {
    // User A proposes
    $meetingPoint = MeetingPoint::create([
        'offer_id'    => $this->offer->id,
        'proposed_by' => $this->userA->id,
        'latitude'    => -6.1950,
        'longitude'   => 106.8230,
        'place_name'  => 'Grand Indonesia',
        'status'      => 'proposed',
    ]);

    // User A attempts to agree to their own proposal
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/{$meetingPoint->id}/agree");

    $response->assertStatus(403);
    expect($meetingPoint->fresh()->status)->toBe('proposed');
});

test('other participant can agree to proposal and it becomes locked', function () {
    // User A proposes
    $meetingPoint = MeetingPoint::create([
        'offer_id'    => $this->offer->id,
        'proposed_by' => $this->userA->id,
        'latitude'    => -6.1950,
        'longitude'   => 106.8230,
        'place_name'  => 'Grand Indonesia Mall',
        'status'      => 'proposed',
    ]);

    // User B agrees
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/{$meetingPoint->id}/agree");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'locked')
        ->assertJsonPath('data.agreed_by', $this->userB->id);

    $freshPoint = $meetingPoint->fresh();
    expect($freshPoint->status)->toBe('locked');
    expect($freshPoint->locked_at)->not->toBeNull();
    expect($freshPoint->agreed_by)->toBe($this->userB->id);

    // Verify system chat message created
    $this->assertDatabaseHas('chats', [
        'offer_id'  => $this->offer->id,
        'sender_id' => $this->userB->id,
        'type'      => 'system',
    ]);
});

test('only one meeting point is locked per offer (new agreed supersedes previous locked)', function () {
    // 1. Initial locked meeting point
    $firstPoint = MeetingPoint::create([
        'offer_id'           => $this->offer->id,
        'proposed_by'        => $this->userA->id,
        'latitude'           => -6.1950,
        'longitude'          => 106.8230,
        'place_name'         => 'Lokasi Pertama (GI)',
        'status'             => 'locked',
        'agreed_by'          => $this->userB->id,
        'agreed_by_other_at' => now(),
        'locked_at'          => now(),
    ]);

    // 2. Later, User B proposes a new meeting point
    $newPoint = MeetingPoint::create([
        'offer_id'    => $this->offer->id,
        'proposed_by' => $this->userB->id,
        'latitude'    => -6.2250,
        'longitude'   => 106.8000,
        'place_name'  => 'Lokasi Kedua (Pacific Place)',
        'status'      => 'proposed',
    ]);

    // First point must still be locked while newPoint is only proposed
    expect($firstPoint->fresh()->status)->toBe('locked');

    // 3. User A agrees to the new proposal
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/{$newPoint->id}/agree");

    $response->assertStatus(200);

    // Now firstPoint must be superseded, and newPoint must be locked
    expect($firstPoint->fresh()->status)->toBe('superseded');
    expect($newPoint->fresh()->status)->toBe('locked');

    // Exactly 1 locked point for this offer
    $lockedCount = MeetingPoint::where('offer_id', $this->offer->id)->where('status', 'locked')->count();
    expect($lockedCount)->toBe(1);
});

test('counter proposal marks previous proposed meeting point as superseded', function () {
    // User A proposes point 1
    $point1 = MeetingPoint::create([
        'offer_id'    => $this->offer->id,
        'proposed_by' => $this->userA->id,
        'latitude'    => -6.1950,
        'longitude'   => 106.8230,
        'place_name'  => 'Grand Indonesia',
        'status'      => 'proposed',
    ]);

    // User B counters with point 2
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->postJson("/api/offers/{$this->offer->id}/meeting-point/counter", [
            'latitude'   => -6.1750,
            'longitude'  => 106.8250,
            'place_name' => 'Sarinah Thamrin',
            'notes'      => 'Lebih dekat dari stasiun MRT',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.place_name', 'Sarinah Thamrin');

    // Point 1 is now superseded
    expect($point1->fresh()->status)->toBe('superseded');

    // New point is proposed
    $point2 = MeetingPoint::where('place_name', 'Sarinah Thamrin')->first();
    expect($point2->status)->toBe('proposed');
    expect($point2->proposed_by)->toBe($this->userB->id);
});

test('user can get meeting point details with active locked and pending states', function () {
    MeetingPoint::create([
        'offer_id'           => $this->offer->id,
        'proposed_by'        => $this->userA->id,
        'latitude'           => -6.1950,
        'longitude'          => 106.8230,
        'place_name'         => 'Mall Kelapa Gading',
        'status'             => 'locked',
        'agreed_by'          => $this->userB->id,
        'agreed_by_other_at' => now(),
        'locked_at'          => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->getJson("/api/offers/{$this->offer->id}/meeting-point");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.locked.place_name', 'Mall Kelapa Gading')
        ->assertJsonPath('data.locked.status', 'locked')
        ->assertJsonPath('data.can_propose', true);
});
