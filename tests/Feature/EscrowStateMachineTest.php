<?php

use App\Enums\EscrowStatus;
use App\Exceptions\InvalidEscrowTransitionException;
use App\Models\Category;
use App\Models\Chat;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\EscrowStateMachine;

beforeEach(function () {
    $this->stateMachine = new EscrowStateMachine();

    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'laptop',
    ]);

    $this->userA = User::factory()->create(['name' => 'User A']);
    $this->userB = User::factory()->create(['name' => 'User B']);

    $this->itemA = Item::create([
        'user_id' => $this->userA->id,
        'category_id' => $this->category->id,
        'title' => 'Item A (500k)',
        'description' => 'Desc A',
        'condition' => 'bekas_baik',
        'estimated_price' => 500000,
        'status' => 'active',
    ]);

    $this->itemB = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'Item B (800k)',
        'description' => 'Desc B',
        'condition' => 'bekas_baik',
        'estimated_price' => 800000,
        'status' => 'active',
    ]);

    $this->offer = Offer::create([
        'offerer_user_id' => $this->userA->id,
        'offerer_item_id' => $this->itemA->id,
        'target_user_id' => $this->userB->id,
        'target_item_id' => $this->itemB->id,
        'payment_method' => 'gateway',
        'status' => 'matched',
        'offerer_approved' => true,
        'target_approved' => true,
    ]);
});

test('calculatePriceDifferenceAndParties correctly determines payer and receiver', function () {
    // User A has 500k item, User B has 800k item -> diff = 300k, payer = User A, receiver = User B
    $res = $this->offer->calculatePriceDifferenceAndParties();
    expect($res['price_difference'])->toBe(300000)
        ->and($res['payer_id'])->toBe($this->userA->id)
        ->and($res['receiver_id'])->toBe($this->userB->id);

    // Reverse: Target item is cheaper (400k) than Offerer (500k) -> diff = 100k, payer = User B, receiver = User A
    $cheapItem = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'Item Murah (400k)',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 400000,
        'status' => 'active',
    ]);

    $this->offer->update(['target_item_id' => $cheapItem->id]);
    $this->offer->unsetRelation('targetItem');

    $res2 = $this->offer->calculatePriceDifferenceAndParties();
    expect($res2['price_difference'])->toBe(100000)
        ->and($res2['payer_id'])->toBe($this->userB->id)
        ->and($res2['receiver_id'])->toBe($this->userA->id);

    // Equal price -> diff = 0, payer = null, receiver = null
    $equalItem = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'Item Sama (500k)',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 500000,
        'status' => 'active',
    ]);
    $this->offer->update(['target_item_id' => $equalItem->id]);
    $this->offer->unsetRelation('targetItem');

    $res3 = $this->offer->calculatePriceDifferenceAndParties();
    expect($res3['price_difference'])->toBe(0)
        ->and($res3['payer_id'])->toBeNull()
        ->and($res3['receiver_id'])->toBeNull();
});

test('valid standard escrow workflow: WAITING_PAYMENT -> HELD -> WAITING_CONFIRMATION -> COMPLETED', function () {
    // 1. Initial transition to WAITING_PAYMENT
    $offer = $this->stateMachine->transition($this->offer, EscrowStatus::WAITING_PAYMENT, [
        'price_difference' => 300000,
        'payer_id' => $this->userA->id,
        'receiver_id' => $this->userB->id,
    ]);
    expect($offer->escrow_status)->toBe(EscrowStatus::WAITING_PAYMENT);

    // 2. HELD: Gateway confirmed payment (settlement) -> generates 6 digit handover_code
    $offer = $this->stateMachine->transition($offer, EscrowStatus::HELD);
    expect($offer->escrow_status)->toBe(EscrowStatus::HELD)
        ->and($offer->handover_code)->not->toBeNull()
        ->and(strlen($offer->handover_code))->toBe(6);

    // 3. WAITING_CONFIRMATION: Handover started
    $offer = $this->stateMachine->transition($offer, EscrowStatus::WAITING_CONFIRMATION);
    expect($offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION);

    // 4. COMPLETED: Both parties confirmed
    $offer = $this->stateMachine->transition($offer, EscrowStatus::COMPLETED);
    expect($offer->escrow_status)->toBe(EscrowStatus::COMPLETED)
        ->and($offer->released_at)->not->toBeNull();
});

test('dispute workflow: HELD / WAITING_CONFIRMATION -> DISPUTED -> REFUNDED', function () {
    $offer = $this->stateMachine->transition($this->offer, EscrowStatus::WAITING_PAYMENT, ['price_difference' => 200000]);
    $offer = $this->stateMachine->transition($offer, EscrowStatus::HELD);
    $offer = $this->stateMachine->transition($offer, EscrowStatus::WAITING_CONFIRMATION);

    // Dispute raised by buyer
    $offer = $this->stateMachine->transition($offer, EscrowStatus::DISPUTED);
    expect($offer->escrow_status)->toBe(EscrowStatus::DISPUTED);

    // Admin resolves dispute with refund to payer
    $offer = $this->stateMachine->transition($offer, EscrowStatus::REFUNDED);
    expect($offer->escrow_status)->toBe(EscrowStatus::REFUNDED);
});

test('expired workflow: WAITING_PAYMENT -> EXPIRED', function () {
    $offer = $this->stateMachine->transition($this->offer, EscrowStatus::WAITING_PAYMENT);
    $offer = $this->stateMachine->transition($offer, EscrowStatus::EXPIRED);
    expect($offer->escrow_status)->toBe(EscrowStatus::EXPIRED);
});

test('invalid transitions are strictly rejected with InvalidEscrowTransitionException', function () {
    // Directly jumping from WAITING_PAYMENT to COMPLETED is forbidden
    $offer = $this->stateMachine->transition($this->offer, EscrowStatus::WAITING_PAYMENT);

    expect(fn () => $this->stateMachine->transition($offer, EscrowStatus::COMPLETED))
        ->toThrow(InvalidEscrowTransitionException::class);

    // From COMPLETED, no further transitions are allowed (terminal)
    $held = $this->stateMachine->transition($offer, EscrowStatus::HELD);
    $waiting = $this->stateMachine->transition($held, EscrowStatus::WAITING_CONFIRMATION);
    $completed = $this->stateMachine->transition($waiting, EscrowStatus::COMPLETED);

    expect(fn () => $this->stateMachine->transition($completed, EscrowStatus::REFUNDED))
        ->toThrow(InvalidEscrowTransitionException::class);
});

test('escrow status transition automatically dispatches notification in offer chat', function () {
    expect(Chat::where('offer_id', $this->offer->id)->count())->toBe(0);

    $this->stateMachine->transition($this->offer, EscrowStatus::WAITING_PAYMENT, ['price_difference' => 150000]);

    $chat = Chat::where('offer_id', $this->offer->id)->latest()->first();
    expect($chat)->not->toBeNull()
        ->and($chat->type)->toBe('system')
        ->and($chat->message)->toContain('150.000');
});

test('wallet credit and debit update balance atomically and record ledger', function () {
    $wallet = $this->userA->getOrCreateWallet();
    expect($wallet->balance)->toBe(0);

    // Credit Rp 500.000
    $txCredit = $wallet->credit(500000, 'escrow_release', $this->offer);
    $wallet->refresh();

    expect($wallet->balance)->toBe(500000)
        ->and($txCredit->type)->toBe('credit')
        ->and($txCredit->amount)->toBe(500000)
        ->and($txCredit->balance_after)->toBe(500000)
        ->and($txCredit->reason)->toBe('escrow_release');

    // Debit Rp 200.000
    $txDebit = $wallet->debit(200000, 'withdrawal');
    $wallet->refresh();

    expect($wallet->balance)->toBe(300000)
        ->and($txDebit->type)->toBe('debit')
        ->and($txDebit->amount)->toBe(200000)
        ->and($txDebit->balance_after)->toBe(300000);
});

test('wallet debit cannot exceed balance (anti-negative balance constraint)', function () {
    $wallet = $this->userA->getOrCreateWallet();
    $wallet->credit(100000, 'escrow_release');

    expect(fn () => $wallet->debit(150000, 'withdrawal'))
        ->toThrow(RuntimeException::class, 'Saldo tidak mencukupi');

    $wallet->refresh();
    expect($wallet->balance)->toBe(100000); // Saldo tetap utuh
});

test('wallet_transactions ledger rows cannot be updated or deleted (append-only enforcement)', function () {
    $wallet = $this->userA->getOrCreateWallet();
    $tx = $wallet->credit(100000, 'escrow_release');

    // Attempting update on ledger row throws RuntimeException
    expect(fn () => $tx->update(['amount' => 999999]))
        ->toThrow(RuntimeException::class, 'dilarang diubah');

    // Attempting delete on ledger row throws RuntimeException
    expect(fn () => $tx->delete())
        ->toThrow(RuntimeException::class, 'dilarang dihapus');
});
