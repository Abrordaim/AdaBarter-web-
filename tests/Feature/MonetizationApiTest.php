<?php

use App\Models\BoostPackage;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Models\VipPlan;
use Database\Seeders\BoostPackageSeeder;
use Database\Seeders\VipPlanSeeder;

beforeEach(function () {
    $this->seed(VipPlanSeeder::class);
    $this->seed(BoostPackageSeeder::class);

    $this->category = Category::create([
        'name' => 'Hobi & Koleksi',
        'slug' => 'hobi-koleksi',
        'icon' => 'sparkles',
    ]);

    $this->user = User::factory()->create([
        'name' => 'Doni Barterer',
        'email' => 'doni@adabarter.com',
        'free_post_quota' => 3,
        'bonus_post_quota' => 0,
        'boost_quota' => 0,
        'is_vip' => false,
        'city' => 'Bandung',
    ]);

    $this->item = Item::create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'title' => 'Gitar Fender Stratocaster',
        'description' => 'Original made in Japan, kondisi mulus',
        'condition' => 'bekas_seperti_baru',
        'estimated_price' => 8500000,
        'city' => 'Bandung',
        'status' => 'active',
        'is_boosted' => false,
    ]);

    $this->token = $this->user->createToken('test')->plainTextToken;
});

test('public can view all monetization packages and plans from database', function () {
    $response = $this->getJson('/api/monetization/plans');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'subscription_plans',
                'boost_packages',
            ],
        ]);

    expect($response->json('data.subscription_plans'))->toHaveCount(3);
    expect($response->json('data.boost_packages'))->toHaveCount(3);

    // Verify first subscription plan has bonus quota fields
    $firstVip = $response->json('data.subscription_plans.0');
    expect($firstVip['id'])->toBeInt();
    expect($firstVip)->toHaveKey('bonus_post_quota');
    expect($firstVip)->toHaveKey('bonus_boost_quota');

    // Verify first boost package has integer ID and days
    $firstBoost = $response->json('data.boost_packages.0');
    expect($firstBoost['id'])->toBeInt();
    expect($firstBoost['days'])->toBe(3);
});

test('user can subscribe to vip plan via localhost payment simulation using plan integer id', function () {
    $vipPlan = VipPlan::first();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/subscribe', [
            'plan_id' => $vipPlan->id,
            'payment_method' => 'QRIS (Simulasi Localhost)',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.plan.id', $vipPlan->id);

    $this->user->refresh();
    expect($this->user->is_vip)->toBeTrue();
    expect($this->user->isVip())->toBeTrue();
    expect($this->user->canPost())->toBeTrue();

    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $this->user->id,
        'plan' => 'vip',
        'price_paid' => $vipPlan->price,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'type' => 'subscription',
        'amount' => $vipPlan->price,
        'status' => 'completed',
    ]);
});

test('subscribing with non-existent plan id returns validation error', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/subscribe', [
            'plan_id' => 99999,
            'payment_method' => 'QRIS',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['plan_id']);
});

test('user can boost an item listing with boost_package_id and non-owner cannot boost it', function () {
    $boostPkg = BoostPackage::where('days', 7)->first();

    // 1. Owner boosts item using boost_package_id
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost", [
            'boost_package_id' => $boostPkg->id,
            'payment_method' => 'BCA Virtual Account (Simulasi)',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->item->refresh();
    expect($this->item->is_boosted)->toBeTrue();
    expect($this->item->boost_expires_at)->not->toBeNull();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'type' => 'boost',
        'amount' => $boostPkg->price,
        'status' => 'completed',
    ]);

    // 2. Another user tries to boost owner's item
    auth()->forgetGuards();
    $otherUser = User::factory()->create(['email' => 'other@adabarter.com']);
    $otherToken = $otherUser->createToken('other')->plainTextToken;

    $forbiddenResponse = $this->withHeader('Authorization', "Bearer {$otherToken}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost", [
            'boost_package_id' => $boostPkg->id,
        ]);

    $forbiddenResponse->assertStatus(403);
});

test('user can also boost item listing using legacy days parameter', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost", [
            'days' => 3,
            'payment_method' => 'QRIS (Simulasi)',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->item->refresh();
    expect($this->item->is_boosted)->toBeTrue();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'type' => 'boost',
        'amount' => 15000,
        'status' => 'completed',
    ]);
});

test('user can boost item using vip boost quota for free', function () {
    // Give user boost_quota
    $this->user->update(['boost_quota' => 2]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost/use-quota");

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->item->refresh();
    $this->user->refresh();

    expect($this->item->is_boosted)->toBeTrue();
    expect($this->user->boost_quota)->toBe(1); // decremented by 1
});

test('boost with quota fails when user has no quota', function () {
    // user has boost_quota = 0 (set in beforeEach)
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost/use-quota");

    $response->assertStatus(422);
});

test('user can retrieve full transaction receipt history', function () {
    $vipPlan = VipPlan::first();
    $boostPkg = BoostPackage::first();

    // Perform two transactions: VIP subscribe + item boost
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/subscribe', ['plan_id' => $vipPlan->id]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost", [
            'boost_package_id' => $boostPkg->id,
            'payment_method' => 'GoPay (Simulasi)',
        ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/monetization/transactions');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $transactions = $response->json('data');
    expect($transactions)->toHaveCount(2);

    $types = collect($transactions)->pluck('type')->toArray();
    expect($types)->toContain('boost');
    expect($types)->toContain('subscription');
});

test('user can create vip payment snap token', function () {
    $vipPlan = VipPlan::first();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/vip/pay', [
            'plan_id' => $vipPlan->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'order_id',
                'gross_amount',
                'redirect_url',
                'plan',
            ],
        ]);

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.gross_amount'))->toBe((int) $vipPlan->price);

    $this->assertDatabaseHas('payments', [
        'user_id' => $this->user->id,
        'type' => 'vip',
        'reference_id' => $vipPlan->id,
        'order_id' => $response->json('data.order_id'),
    ]);
});

test('user can create boost payment snap token', function () {
    $boostPkg = BoostPackage::first();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost/pay", [
            'boost_package_id' => $boostPkg->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'order_id',
                'gross_amount',
                'redirect_url',
                'item',
                'package',
            ],
        ]);

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.gross_amount'))->toBe((int) $boostPkg->price);

    $this->assertDatabaseHas('payments', [
        'user_id' => $this->user->id,
        'type' => 'boost',
        'reference_id' => $this->item->id,
        'order_id' => $response->json('data.order_id'),
    ]);
});

