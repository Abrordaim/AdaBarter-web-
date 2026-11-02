<?php

use App\Models\BarterTracking;
use App\Models\Category;
use App\Models\Chat;
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
        'city' => 'Yogyakarta',
    ]);

    $this->userB = User::factory()->create([
        'name' => 'Bambang Pemilik Target',
        'email' => 'bambang@adabarter.com',
        'city' => 'Sleman',
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
        'description' => 'Kondisi mulus',
        'condition' => 'bekas_baik',
        'estimated_price' => 4500000,
        'city' => 'Yogyakarta',
        'status' => 'active',
    ]);

    $this->itemB = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'Samsung S21',
        'description' => 'Lengkap dus original',
        'condition' => 'bekas_seperti_baru',
        'estimated_price' => 5000000,
        'city' => 'Sleman',
        'status' => 'active',
    ]);

    $this->tokenA = $this->userA->createToken('testA')->plainTextToken;
    $this->tokenB = $this->userB->createToken('testB')->plainTextToken;
    $this->tokenC = $this->userC->createToken('testC')->plainTextToken;

    // Matched offer
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

    // Locked meeting point: Ambarrukmo Plaza Jogja (-7.782800, 110.401100)
    $this->lockedMeetingPoint = MeetingPoint::create([
        'offer_id'           => $this->offer->id,
        'proposed_by'        => $this->userA->id,
        'latitude'           => -7.782800,
        'longitude'          => 110.401100,
        'place_name'         => 'Plaza Ambarrukmo, Yogyakarta',
        'address'            => 'Lobi Timur depan Starbucks',
        'meet_at'            => now()->addDays(1)->setHour(15),
        'status'             => 'locked',
        'agreed_by'          => $this->userB->id,
        'agreed_by_other_at' => now(),
        'locked_at'          => now(),
    ]);
});

test('non-participant cannot view or update tracking (IDOR protection)', function () {
    $resView = $this->withHeader('Authorization', "Bearer {$this->tokenC}")
        ->getJson("/api/offers/{$this->offer->id}/tracking");

    $resView->assertStatus(403);

    $resUpdate = $this->withHeader('Authorization', "Bearer {$this->tokenC}")
        ->postJson("/api/offers/{$this->offer->id}/tracking/location", [
            'latitude'  => -7.782800,
            'longitude' => 110.401100,
        ]);

    $resUpdate->assertStatus(403);
});

test('cannot track if offer has no locked meeting point', function () {
    // Delete locked meeting point
    $this->lockedMeetingPoint->delete();

    $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->getJson("/api/offers/{$this->offer->id}/tracking");

    $res->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('participant can update location and transition to otw status with chat message', function () {
    // User A reports location (Tugu Jogja: -7.782900, 110.367000)
    $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/tracking/location", [
            'latitude'  => -7.782900,
            'longitude' => 110.367000,
            'status'    => 'otw',
            'heading'   => 90.0,
            'speed'     => 40.5,
        ]);

    $res->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'otw')
        ->assertJsonPath('data.is_sharing', true);

    // Verify distance calculated
    $distance = $res->json('data.distance_to_target_meters');
    expect($distance)->toBeGreaterThan(3000); // Tugu to Amplaz is ~3.7 km

    // Verify system chat generated
    $chat = Chat::where('offer_id', $this->offer->id)
        ->where('type', 'system')
        ->latest()
        ->first();

    expect($chat)->not->toBeNull();
    expect($chat->message)->toContain('mulai berangkat menuju lokasi pertemuan');
});

test('participant can view tracking status and see partner position and distance', function () {
    // User A updates location (Tugu: -7.782900, 110.367000)
    BarterTracking::create([
        'offer_id'                  => $this->offer->id,
        'user_id'                   => $this->userA->id,
        'latitude'                  => -7.782900,
        'longitude'                 => 110.367000,
        'status'                    => 'otw',
        'is_sharing'                => true,
        'distance_to_target_meters' => 3700,
        'last_seen_at'              => now(),
    ]);

    // User B updates location (Jalan Gejayan: -7.770000, 110.390000)
    BarterTracking::create([
        'offer_id'                  => $this->offer->id,
        'user_id'                   => $this->userB->id,
        'latitude'                  => -7.770000,
        'longitude'                 => 110.390000,
        'status'                    => 'otw',
        'is_sharing'                => true,
        'distance_to_target_meters' => 1800,
        'last_seen_at'              => now(),
    ]);

    // User A gets tracking status
    $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->getJson("/api/offers/{$this->offer->id}/tracking");

    $res->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.meeting_point.place_name', 'Plaza Ambarrukmo, Yogyakarta')
        ->assertJsonPath('data.my_tracking.status', 'otw')
        ->assertJsonPath('data.partner.name', 'Bambang Pemilik Target')
        ->assertJsonPath('data.partner_tracking.status', 'otw')
        ->assertJsonPath('data.partner_tracking.is_sharing', true);

    expect($res->json('data.distance_between_users_meters'))->toBeGreaterThan(1000);
});

test('status automatically or manually marked arrived when close to target', function () {
    // User A arrives right at Amplaz (-7.782800, 110.401100)
    $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/tracking/location", [
            'latitude'  => -7.782800,
            'longitude' => 110.401100,
            'status'    => 'otw',
        ]);

    $res->assertStatus(200)
        ->assertJsonPath('data.status', 'arrived');

    $chat = Chat::where('offer_id', $this->offer->id)
        ->where('type', 'system')
        ->latest()
        ->first();

    expect($chat->message)->toContain('telah sampai di lokasi pertemuan');
});

test('user can stop tracking and coordinates become hidden', function () {
    // User A was sharing
    BarterTracking::create([
        'offer_id'                  => $this->offer->id,
        'user_id'                   => $this->userA->id,
        'latitude'                  => -7.782900,
        'longitude'                 => 110.367000,
        'status'                    => 'otw',
        'is_sharing'                => true,
        'distance_to_target_meters' => 3700,
        'last_seen_at'              => now(),
    ]);

    // User A stops sharing
    $resStop = $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/offers/{$this->offer->id}/tracking/stop");

    $resStop->assertStatus(200)
        ->assertJsonPath('data.is_sharing', false)
        ->assertJsonPath('data.status', 'idle');

    // Reset auth guard so User B's token is used for the next request
    $this->app['auth']->forgetGuards();

    // When User B views tracking, User A's coordinates are hidden
    $resView = $this->actingAs($this->userB, 'sanctum')
        ->getJson("/api/offers/{$this->offer->id}/tracking");

    $resView->assertStatus(200)
        ->assertJsonPath('data.partner_tracking.is_sharing', false)
        ->assertJsonMissing(['latitude' => -7.782900]);
});

test('validates coordinate range', function () {
    $res = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/tracking/location", [
            'latitude'  => 150.0, // Invalid latitude > 90
            'longitude' => 110.401100,
        ]);

    $res->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);
});

test('cannot view or update tracking when offer is completed', function () {
    $this->offer->update(['status' => 'completed']);

    $resView = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->getJson("/api/offers/{$this->offer->id}/tracking");

    $resView->assertStatus(422)
        ->assertJsonPath('success', false);

    $resUpdate = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/offers/{$this->offer->id}/tracking/location", [
            'latitude'  => -7.782800,
            'longitude' => 110.401100,
        ]);

    $resUpdate->assertStatus(422)
        ->assertJsonPath('success', false);
});
