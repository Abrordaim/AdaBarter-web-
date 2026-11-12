<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use App\Models\UserPenalty;
use App\Services\PenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'smartphone',
    ]);
});

// -------------------------------------------------------------------------
// 1. Tier escalation: 0 → 1 → 2 → 3 → 4 (Banned)
// -------------------------------------------------------------------------
test('awardPoints escalates penalty tiers correctly from Tier 0 to Tier 4', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user  = User::factory()->create(['account_status' => 'active', 'penalty_points' => 0]);
    $service = app(PenaltyService::class);

    // Tier 0: 2 points → 'warned', no restrictions
    $service->awardPoints($user, 2, 'katalog_menyesatkan', 'Peringatan 1', null, $admin->id);
    $user->refresh();
    expect($user->penalty_points)->toBe(2)
        ->and($user->restriction_tier)->toBe(0)
        ->and($user->account_status)->toBe('warned')
        ->and($user->active_restrictions)->toBe([]);

    // Tier 1: +3 points = 5 points → restricted: no_boost, de_rank
    $service->awardPoints($user, 3, 'spam_chat', 'Pelanggaran sedang', null, $admin->id);
    $user->refresh();
    expect($user->penalty_points)->toBe(5)
        ->and($user->restriction_tier)->toBe(1)
        ->and($user->account_status)->toBe('restricted')
        ->and($user->active_restrictions)->toContain('no_boost');

    // Tier 2: +3 points = 8 points → restricted: no_post, no_offer, no_boost
    $service->awardPoints($user, 3, 'batal_sepihak', 'Batal berulang', null, $admin->id);
    $user->refresh();
    expect($user->penalty_points)->toBe(8)
        ->and($user->restriction_tier)->toBe(2)
        ->and($user->account_status)->toBe('restricted')
        ->and($user->active_restrictions)->toContain('no_post')
        ->and($user->active_restrictions)->toContain('no_offer');

    // Tier 3: +3 points = 11 points → no_direct_chat, hide_items added
    $service->awardPoints($user, 3, 'katalog_menyesatkan', 'Pelanggaran lanjutan', null, $admin->id);
    $user->refresh();
    expect($user->penalty_points)->toBe(11)
        ->and($user->restriction_tier)->toBe(3)
        ->and($user->account_status)->toBe('restricted')
        ->and($user->active_restrictions)->toContain('no_direct_chat')
        ->and($user->active_restrictions)->toContain('hide_items');

    // Tier 4: +4 points = 15 points → Permanent Ban
    $service->awardPoints($user, 4, 'pelanggaran_berat', 'Akumulasi 15 poin fatal', null, $admin->id);
    $user->refresh();
    expect($user->penalty_points)->toBe(15)
        ->and($user->restriction_tier)->toBe(4)
        ->and($user->account_status)->toBe('banned')
        ->and($user->is_banned)->toBeTrue();
});

// -------------------------------------------------------------------------
// 2. Middleware blocks restricted features (no_post, no_direct_chat)
// -------------------------------------------------------------------------
test('middleware blocks posting items when user has no_post restriction', function () {
    $userNoPost = User::factory()->create([
        'account_status'   => 'restricted',
        'restriction_tier' => 2,
        'active_restrictions' => ['no_post', 'no_offer'],
    ]);
    $token = $userNoPost->createToken('test')->plainTextToken;

    $file = UploadedFile::fake()->image('item.jpg');

    $response = $this->withToken($token)->postJson('/api/items', [
        'category_id' => $this->category->id,
        'title'       => 'Barang Dilarang Upload',
        'description' => 'Mencoba upload saat dibatasi',
        'condition'   => 'bekas_baik',
        'images'      => [$file],
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error_code', 'FEATURE_RESTRICTED')
        ->assertJsonPath('data.restriction', 'no_post');
});

test('middleware blocks starting a new direct chat when user has no_direct_chat restriction', function () {
    $userNoChat = User::factory()->create([
        'account_status'   => 'restricted',
        'restriction_tier' => 3,
        'active_restrictions' => ['no_direct_chat'],
    ]);

    $itemOwner = User::factory()->create();
    $targetItem = Item::create([
        'user_id'     => $itemOwner->id,
        'category_id' => $this->category->id,
        'title'       => 'MacBook Pro Test',
        'description' => 'Test item for chat restriction',
        'condition'   => 'bekas_baik',
        'status'      => 'active',
    ]);

    $chatToken = $userNoChat->createToken('test')->plainTextToken;

    $response = $this->withToken($chatToken)->postJson("/api/items/{$targetItem->id}/direct-chat");

    $response->assertStatus(403)
        ->assertJsonPath('error_code', 'FEATURE_RESTRICTED')
        ->assertJsonPath('data.restriction', 'no_direct_chat');
});

// -------------------------------------------------------------------------
// 3. Fair Play: restricted user CAN still chat in existing matched offers
// -------------------------------------------------------------------------
test('fair play: restricted user can still send messages in existing matched barter chat', function () {
    // userA is restricted (heavy tier) but has an active matched offer with userB
    $userA = User::factory()->create([
        'account_status'   => 'restricted',
        'restriction_tier' => 3,
        'active_restrictions' => ['no_direct_chat', 'no_offer', 'no_post'],
    ]);
    $userB = User::factory()->create();

    $itemA = Item::create([
        'user_id'     => $userA->id,
        'category_id' => $this->category->id,
        'title'       => 'Barang A',
        'description' => 'Deskripsi barang A',
        'condition'   => 'bekas_baik',
        'status'      => 'active',
    ]);

    $itemB = Item::create([
        'user_id'     => $userB->id,
        'category_id' => $this->category->id,
        'title'       => 'Barang B',
        'description' => 'Deskripsi barang B',
        'condition'   => 'bekas_baik',
        'status'      => 'active',
    ]);

    $offer = Offer::create([
        'offerer_user_id'  => $userB->id,
        'target_user_id'   => $userA->id,
        'offerer_item_id'  => $itemB->id,
        'target_item_id'   => $itemA->id,
        'status'           => 'matched',
        'offerer_approved' => true,
        'target_approved'  => true,
    ]);

    $token = $userA->createToken('test')->plainTextToken;

    // Should be allowed — this is an existing matched trade chat, NOT a new direct chat
    $msgRes = $this->withToken($token)->postJson("/api/offers/{$offer->id}/chats", [
        'message' => 'Halo mitra, transaksi barter kita tetap saya kirimkan sesuai kesepakatan.',
    ]);

    $msgRes->assertStatus(201)
        ->assertJsonPath('success', true);
});

// -------------------------------------------------------------------------
// 4. User can view account health & submit appeal, Admin resolves it
// -------------------------------------------------------------------------
test('user can view account health and submit an appeal that admin then resolves', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user  = User::factory()->create();
    $service = app(PenaltyService::class);

    $penalty = $service->awardPoints($user, 3, 'katalog_menyesatkan', 'Foto tidak sesuai barang asli', null, $admin->id);

    $token = $user->createToken('test')->plainTextToken;

    // 1. View Account Health
    $healthRes = $this->withToken($token)->getJson('/api/user/account-health');
    $healthRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.penalty_points', 3)
        ->assertJsonPath('data.penalties.0.can_appeal', true);

    // 2. Submit Appeal with evidence photo
    $appealEvidence = UploadedFile::fake()->image('bukti.jpg');
    $appealRes = $this->withToken($token)->postJson("/api/user/penalties/{$penalty->id}/appeal", [
        'reason'   => 'Saya menyertakan foto kemasan asli yang belum dibuka sebagai sanggahan yang sah.',
        'evidence' => $appealEvidence,
    ]);

    $appealRes->assertStatus(200)
        ->assertJsonPath('success', true);

    $penalty->refresh();
    expect($penalty->appeal_status)->toBe('pending')
        ->and($penalty->appeal_evidence)->not->toBeNull();

    // 3. Admin Accepts Appeal → poin dicabut, akun dipulihkan
    $service->resolveAppeal($penalty->id, true, 'Sanggahan diterima, foto kemasan asli valid.', $admin->id);

    $penalty->refresh();
    $user->refresh();

    expect($penalty->status)->toBe('revoked')
        ->and($penalty->appeal_status)->toBe('accepted')
        ->and($user->penalty_points)->toBe(0)
        ->and($user->account_status)->toBe('active')
        ->and($user->active_restrictions)->toBe([]);
});

// -------------------------------------------------------------------------
// 5. Artisan command expires old penalties and recalculates user status
// -------------------------------------------------------------------------
test('artisan penalties:expire expires penalties older than 90 days and restores account status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user  = User::factory()->create();
    $service = app(PenaltyService::class);

    $penalty = $service->awardPoints($user, 3, 'spam_chat', 'Spam lama', null, $admin->id);

    // Set expires_at ke masa lalu agar expired
    $penalty->expires_at = now()->subDay();
    $penalty->save();

    Artisan::call('penalties:expire');

    $penalty->refresh();
    $user->refresh();

    expect($penalty->status)->toBe('expired')
        ->and($user->penalty_points)->toBe(0)
        ->and($user->account_status)->toBe('active');
});

// -------------------------------------------------------------------------
// 6. Appeal cannot be submitted twice on the same penalty
// -------------------------------------------------------------------------
test('user cannot submit appeal twice for same penalty', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user  = User::factory()->create();
    $service = app(PenaltyService::class);

    $penalty = $service->awardPoints($user, 2, 'katalog_menyesatkan', 'Pelanggaran 1', null, $admin->id);

    $token = $user->createToken('test')->plainTextToken;

    // First appeal should succeed
    $this->withToken($token)->postJson("/api/user/penalties/{$penalty->id}/appeal", [
        'reason' => 'Alasan pertama yang valid dan cukup panjang untuk disampaikan.',
    ])->assertStatus(200);

    // Second appeal should fail
    $this->withToken($token)->postJson("/api/user/penalties/{$penalty->id}/appeal", [
        'reason' => 'Alasan kedua yang dicoba lagi tapi seharusnya ditolak karena sudah diajukan.',
    ])->assertStatus(422);
});
