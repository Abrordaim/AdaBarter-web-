<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
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

test('public can view all monetization packages and plans', function () {
    $response = $this->getJson('/api/monetization/plans');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'subscription_plans',
                'boost_packages',
                'quota_packages',
            ],
        ]);

    expect($response->json('data.subscription_plans'))->toHaveCount(3);
    expect($response->json('data.boost_packages'))->toHaveCount(3);
    expect($response->json('data.quota_packages'))->toHaveCount(3);
});

test('user can subscribe to vip plan via localhost payment simulation', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/subscribe', [
            'plan_id' => 'vip_1m',
            'payment_method' => 'QRIS (Simulasi Localhost)',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.plan.id', 'vip_1m');

    $this->user->refresh();
    expect($this->user->is_vip)->toBeTrue();
    expect($this->user->isVip())->toBeTrue();
    expect($this->user->canPost())->toBeTrue();

    $this->assertDatabaseHas('subscriptions', [
        'user_id' => $this->user->id,
        'plan' => 'vip',
        'price_paid' => 49000,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'type' => 'subscription',
        'amount' => 49000,
        'status' => 'completed',
    ]);
});

test('user can boost an item listing and non-owner cannot boost it', function () {
    // 1. Owner boosts item for 7 days
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost", [
            'days' => 7,
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
        'amount' => 29000,
        'status' => 'completed',
    ]);

    // 2. Another user tries to boost owner's item
    auth()->forgetGuards();
    $otherUser = User::factory()->create(['email' => 'other@adabarter.com']);
    $otherToken = $otherUser->createToken('other')->plainTextToken;

    $forbiddenResponse = $this->withHeader('Authorization', "Bearer {$otherToken}")
        ->postJson("/api/monetization/items/{$this->item->id}/boost", [
            'days' => 3,
        ]);

    $forbiddenResponse->assertStatus(403);
});

test('user can purchase pay-per-post quota packages', function () {
    expect($this->user->bonus_post_quota)->toBe(0);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/quota/purchase', [
            'slots' => 3,
            'payment_method' => 'GoPay (Simulasi)',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.new_bonus_quota', 3);

    $this->user->refresh();
    expect($this->user->bonus_post_quota)->toBe(3);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $this->user->id,
        'type' => 'pay_per_post',
        'amount' => 25000,
        'status' => 'completed',
    ]);
});

test('user can retrieve full transaction receipt history', function () {
    // Perform two transactions
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/subscribe', ['plan_id' => 'vip_1m']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/monetization/quota/purchase', ['slots' => 1]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/monetization/transactions');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $transactions = $response->json('data');
    expect($transactions)->toHaveCount(2);
    expect($transactions[0]['type'])->toBe('pay_per_post');
    expect($transactions[1]['type'])->toBe('subscription');
});
