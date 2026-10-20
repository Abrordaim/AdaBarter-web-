<?php

use App\Enums\EscrowStatus;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use App\Services\EscrowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Kamera',
        'slug' => 'kamera',
        'icon' => 'camera',
    ]);

    $this->payer = User::factory()->create(['name' => 'Payer Andi']);
    $this->receiver = User::factory()->create(['name' => 'Receiver Budi']);
    $this->stranger = User::factory()->create(['name' => 'Stranger']);

    $this->itemPayer = Item::create([
        'user_id' => $this->payer->id,
        'category_id' => $this->category->id,
        'title' => 'Lensa 50mm',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 1000000,
        'status' => 'active',
    ]);

    $this->itemReceiver = Item::create([
        'user_id' => $this->receiver->id,
        'category_id' => $this->category->id,
        'title' => 'Kamera Sony A6000',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 1500000,
        'status' => 'active',
    ]);

    // Matched offer with 500k escrow waiting confirmation
    $this->offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->itemPayer->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->itemReceiver->id,
        'payment_method' => 'gateway',
        'price_difference' => 500000,
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::WAITING_CONFIRMATION,
        'handover_code' => '654321',
        'handover_attempts' => 0,
        'confirm_deadline_at' => now()->addHours(48),
        'status' => 'matched',
        'offerer_approved' => true,
        'target_approved' => true,
    ]);
});

test('stranger cannot confirm receipt', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    expect(fn () => $escrowService->confirmReceived($this->stranger, $this->offer))
        ->toThrow(AuthorizationException::class);
});

test('single party confirmation does not release funds until both confirm', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    // 1. Payer confirms
    $res = $escrowService->confirmReceived($this->payer, $this->offer);

    expect($res['is_released'])->toBeFalse();
    $this->offer->refresh();
    expect($this->offer->payer_confirmed_at)->not->toBeNull()
        ->and($this->offer->receiver_confirmed_at)->toBeNull()
        ->and($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION);

    // Receiver's wallet still has 0
    $receiverWallet = $this->receiver->getOrCreateWallet();
    expect($receiverWallet->balance)->toBe(0);

    // 2. Receiver confirms -> now both confirmed -> funds released!
    $res2 = $escrowService->confirmReceived($this->receiver, $this->offer);

    expect($res2['is_released'])->toBeTrue();
    $this->offer->refresh();
    expect($this->offer->receiver_confirmed_at)->not->toBeNull()
        ->and($this->offer->escrow_status)->toBe(EscrowStatus::COMPLETED)
        ->and($this->offer->released_at)->not->toBeNull()
        ->and($this->offer->status)->toBe('completed');

    // Receiver's wallet credited 500.000
    $receiverWallet->refresh();
    expect($receiverWallet->balance)->toBe(500000);

    // Items marked as traded
    expect(Item::find($this->itemPayer->id)->status)->toBe('traded')
        ->and(Item::find($this->itemReceiver->id)->status)->toBe('traded');
});

test('receiver verifying handover code instantly confirms both parties and releases funds', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    // Receiver enters correct handover_code '654321' provided by payer
    $res = $escrowService->confirmReceived($this->receiver, $this->offer, '654321');

    expect($res['is_released'])->toBeTrue();
    $this->offer->refresh();
    expect($this->offer->payer_confirmed_at)->not->toBeNull()
        ->and($this->offer->receiver_confirmed_at)->not->toBeNull()
        ->and($this->offer->escrow_status)->toBe(EscrowStatus::COMPLETED)
        ->and($this->offer->released_at)->not->toBeNull();

    $wallet = $this->receiver->getOrCreateWallet();
    expect($wallet->balance)->toBe(500000);
});

test('incorrect handover code increments attempts and fails', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    expect(fn () => $escrowService->confirmReceived($this->receiver, $this->offer, '000000'))
        ->toThrow(DomainException::class, 'Kode serah terima salah');

    $this->offer->refresh();
    expect($this->offer->handover_attempts)->toBe(1)
        ->and($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION);
});

test('exceeding 5 handover attempts locks verification', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);
    $this->offer->update(['handover_attempts' => 5]);

    expect(fn () => $escrowService->confirmReceived($this->receiver, $this->offer, '654321'))
        ->toThrow(DomainException::class, 'Batas maksimal percobaan');
});

test('confirmation is blocked if an open dispute exists', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    // Create open dispute
    Dispute::create([
        'offer_id' => $this->offer->id,
        'reported_by' => $this->payer->id,
        'reason' => 'Barang rusak',
        'description' => 'Kamera tidak menyala sama sekali',
        'status' => 'open',
    ]);

    expect(fn () => $escrowService->confirmReceived($this->payer, $this->offer))
        ->toThrow(DomainException::class, 'Dispute');
});

test('api endpoint /api/offers/{id}/confirm works with auth token', function () {
    $token = $this->payer->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/offers/{$this->offer->id}/confirm");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_released', false);

    $this->offer->refresh();
    expect($this->offer->payer_confirmed_at)->not->toBeNull();
});

test('scheduled command escrow:auto-release releases funds if deadline passed and at least one party confirmed', function () {
    // Payer confirmed, but receiver forgot. Deadline has passed.
    $this->offer->update([
        'payer_confirmed_at' => now()->subDays(3),
        'confirm_deadline_at' => now()->subHours(1),
    ]);

    Artisan::call('escrow:auto-release');

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::COMPLETED)
        ->and($this->offer->released_at)->not->toBeNull();

    $wallet = $this->receiver->getOrCreateWallet();
    expect($wallet->balance)->toBe(500000);
});

test('scheduled command escrow:auto-release does NOT release funds if neither party confirmed', function () {
    // Neither party confirmed, deadline passed
    $this->offer->update([
        'payer_confirmed_at' => null,
        'receiver_confirmed_at' => null,
        'confirm_deadline_at' => now()->subHours(1),
    ]);

    Artisan::call('escrow:auto-release');

    $this->offer->refresh();
    // Remains waiting confirmation, flagged for admin review
    expect($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION)
        ->and($this->offer->released_at)->toBeNull();

    $wallet = $this->receiver->getOrCreateWallet();
    expect($wallet->balance)->toBe(0);
});

test('scheduled command escrow:expire-unpaid expires offers older than 24h still in WAITING_PAYMENT', function () {
    $this->offer->update([
        'escrow_status' => EscrowStatus::WAITING_PAYMENT,
    ]);
    DB::table('offers')->where('id', $this->offer->id)->update([
        'created_at' => now()->subHours(25),
    ]);

    Artisan::call('escrow:expire-unpaid');

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::EXPIRED);
});
