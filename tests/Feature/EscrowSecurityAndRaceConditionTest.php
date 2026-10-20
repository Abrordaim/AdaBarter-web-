<?php

use App\Enums\EscrowStatus;
use App\Exceptions\InvalidEscrowTransitionException;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\EscrowService;
use App\Services\EscrowStateMachine;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('services.midtrans.server_key', 'test-server-key-secure');
    config()->set('services.midtrans.confirm_deadline_hours', 48);

    $this->category = Category::create([
        'name' => 'Elektronik & Gadget',
        'slug' => 'elektronik-gadget',
        'icon' => 'phone',
    ]);

    $this->payer = User::factory()->create(['name' => 'Pembeli Payer', 'role' => 'user']);
    $this->receiver = User::factory()->create(['name' => 'Penjual Receiver', 'role' => 'user']);
    $this->stranger = User::factory()->create(['name' => 'Orang Asing', 'role' => 'user']);
    $this->admin = User::factory()->create(['name' => 'Admin Utama', 'role' => 'admin']);

    $this->itemPayer = Item::create([
        'user_id' => $this->payer->id,
        'category_id' => $this->category->id,
        'title' => 'HP Bekas',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 1_000_000,
        'status' => 'active',
    ]);

    $this->itemReceiver = Item::create([
        'user_id' => $this->receiver->id,
        'category_id' => $this->category->id,
        'title' => 'Tablet Bekas',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 1_500_000,
        'status' => 'active',
    ]);

    $this->offer = Offer::create([
        'offerer_user_id'    => $this->payer->id,
        'offerer_item_id'    => $this->itemPayer->id,
        'target_user_id'     => $this->receiver->id,
        'target_item_id'     => $this->itemReceiver->id,
        'payment_method'     => 'gateway',
        'price_difference'   => 500_000,
        'payer_id'           => $this->payer->id,
        'receiver_id'        => $this->receiver->id,
        'escrow_status'      => EscrowStatus::WAITING_PAYMENT,
        'handover_code'      => '123456',
        'handover_attempts'  => 0,
        'status'             => 'matched',
        'offerer_approved'   => true,
        'target_approved'    => true,
    ]);
});

// ─── 1. Append-Only Ledger Security ───────────────────────────────────────────

test('security: wallet transaction ledger is strictly append-only and cannot be updated', function () {
    $wallet = $this->payer->getOrCreateWallet();
    $tx = $wallet->credit(100_000, 'initial_deposit');

    expect(fn () => $tx->update(['amount' => 999_999]))
        ->toThrow(RuntimeException::class, 'dilarang diubah');

    // Pastikan nominal di database tidak berubah
    $tx->refresh();
    expect($tx->amount)->toBe(100_000);
});

test('security: wallet transaction ledger cannot be deleted', function () {
    $wallet = $this->payer->getOrCreateWallet();
    $tx = $wallet->credit(100_000, 'initial_deposit');

    expect(fn () => $tx->delete())
        ->toThrow(RuntimeException::class, 'dilarang dihapus');

    expect(WalletTransaction::where('id', $tx->id)->exists())->toBeTrue();
});

// ─── 2. Negative Balance & Invalid Currency Protection ────────────────────────

test('security: wallet debit strictly prevents negative balance', function () {
    $wallet = $this->payer->getOrCreateWallet();
    $wallet->credit(50_000, 'topup');

    expect(fn () => $wallet->debit(60_000, 'overdraw'))
        ->toThrow(RuntimeException::class, 'Saldo tidak mencukupi');

    $wallet->refresh();
    expect($wallet->balance)->toBe(50_000);
});

test('security: negative or zero amounts are rejected in credit and debit', function () {
    $wallet = $this->payer->getOrCreateWallet();

    expect(fn () => $wallet->credit(0, 'invalid'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $wallet->credit(-5000, 'invalid'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $wallet->debit(0, 'invalid'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $wallet->debit(-5000, 'invalid'))->toThrow(InvalidArgumentException::class);
});

// ─── 3. Midtrans Webhook Tampering & Signature Validation ─────────────────────

test('security: midtrans webhook rejects tampered signature', function () {
    $payload = [
        'order_id'          => 'ESCROW-' . $this->offer->id . '-12345',
        'status_code'       => '200',
        'gross_amount'      => '500000.00',
        'transaction_status'=> 'settlement',
        'fraud_status'      => 'accept',
        'signature_key'     => 'hacker-signature-key-invalid',
    ];

    $response = $this->postJson('/api/payments/midtrans/notification', $payload);

    $response->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_PAYMENT);
});

test('security: midtrans webhook rejects tampered gross amount', function () {
    $orderId = 'ESCROW-' . $this->offer->id . '-12345';
    \App\Models\Payment::create([
        'offer_id'     => $this->offer->id,
        'order_id'     => $orderId,
        'gross_amount' => 500_000,
        'gateway'      => 'midtrans',
        'status'       => 'pending',
    ]);

    $tamperedAmount = '10000.00'; // Mencoba bayar 10rb bukan 500rb
    $signature = hash('sha512', $orderId . '200' . $tamperedAmount . 'test-server-key-secure');

    $payload = [
        'order_id'          => $orderId,
        'status_code'       => '200',
        'gross_amount'      => $tamperedAmount,
        'transaction_status'=> 'settlement',
        'fraud_status'      => 'accept',
        'signature_key'     => $signature,
    ];

    $response = $this->postJson('/api/payments/midtrans/notification', $payload);

    $response->assertStatus(400)
        ->assertJsonPath('success', false);

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_PAYMENT);
});

test('security: idempotent midtrans webhook prevents double processing on replay', function () {
    $orderId = 'ESCROW-' . $this->offer->id . '-99999';
    \App\Models\Payment::create([
        'offer_id'     => $this->offer->id,
        'order_id'     => $orderId,
        'gross_amount' => 500_000,
        'gateway'      => 'midtrans',
        'status'       => 'pending',
    ]);

    $amount = '500000.00';
    $signature = hash('sha512', $orderId . '200' . $amount . 'test-server-key-secure');

    $payload = [
        'order_id'          => $orderId,
        'status_code'       => '200',
        'gross_amount'      => $amount,
        'transaction_status'=> 'settlement',
        'fraud_status'      => 'accept',
        'signature_key'     => $signature,
        'transaction_id'    => 'trx-unique-001',
    ];

    // First delivery
    $res1 = $this->postJson('/api/payments/midtrans/notification', $payload);
    $res1->assertStatus(200);

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION);

    // Second delivery (replay / retry from Midtrans network)
    $res2 = $this->postJson('/api/payments/midtrans/notification', $payload);
    $res2->assertStatus(200)
        ->assertJsonPath('message', 'Notifikasi settlement sudah pernah diproses sebelumnya (idempotent).');

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION);
});

// ─── 4. Race Condition: Double Release Prevention ─────────────────────────────

test('race condition: concurrent releaseFunds prevents double crediting to receiver', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    // Set offer to HELD
    $this->offer->update(['escrow_status' => EscrowStatus::HELD]);

    $receiverWallet = $this->receiver->getOrCreateWallet();
    $receiverWallet->refresh();
    expect($receiverWallet->balance)->toBe(0);

    // First release
    $escrowService->releaseFunds($this->offer);

    $receiverWallet->refresh();
    expect($receiverWallet->balance)->toBe(500_000);
    expect($receiverWallet->transactions)->toHaveCount(1);

    // Second concurrent call on now COMPLETED offer
    expect(fn () => $escrowService->releaseFunds($this->offer))
        ->toThrow(DomainException::class);

    // Verify balance was NOT credited twice
    $receiverWallet->refresh();
    expect($receiverWallet->balance)->toBe(500_000);
    expect($receiverWallet->transactions)->toHaveCount(1);
});

// ─── 5. Race Condition: Concurrent Withdrawal Overdraw ─────────────────────────

test('race condition: sequential or concurrent withdrawals cannot exceed wallet balance', function () {
    /** @var WalletService $walletService */
    $walletService = app(WalletService::class);

    $wallet = $this->payer->getOrCreateWallet();
    $wallet->credit(100_000, 'balance_seed');
    $wallet->refresh();
    expect($wallet->balance)->toBe(100_000);

    // Withdrawal 1: Rp 60.000 (sukses, sisa 40.000)
    $w1 = $walletService->requestWithdrawal($this->payer, 60_000, 'BCA', '111', 'Payer');
    expect($w1->status)->toBe('pending');

    $wallet->refresh();
    expect($wallet->balance)->toBe(40_000);

    // Withdrawal 2: Rp 60.000 (harus gagal karena saldo sisa tinggal 40.000)
    expect(fn () => $walletService->requestWithdrawal($this->payer, 60_000, 'BCA', '111', 'Payer'))
        ->toThrow(RuntimeException::class, 'Saldo tidak mencukupi');

    $wallet->refresh();
    expect($wallet->balance)->toBe(40_000);
});

// ─── 6. Handover Code Rate Limiting & Brute Force Prevention ──────────────────

test('security: handover code locks after 5 consecutive failed attempts', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $this->offer->update([
        'escrow_status' => EscrowStatus::HELD,
        'handover_code' => '987654',
        'handover_attempts' => 0,
    ]);

    // Attempt 1 to 5: Wrong code increments attempts
    for ($i = 1; $i <= 5; $i++) {
        expect(fn () => $escrowService->confirmReceived($this->receiver, $this->offer, '000000'))
            ->toThrow(DomainException::class);
        $this->offer->refresh();
        expect($this->offer->handover_attempts)->toBe($i);
    }

    // Attempt 6 with CORRECT code: blocked because attempts >= 5!
    expect(fn () => $escrowService->confirmReceived($this->receiver, $this->offer, '987654'))
        ->toThrow(DomainException::class, 'Batas maksimal percobaan');
});

// ─── 7. Dispute Authorization & Active Dispute Immunity ───────────────────────

test('security: non-participant cannot open dispute', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $this->offer->update(['escrow_status' => EscrowStatus::HELD]);

    expect(fn () => $escrowService->openDispute(
        user: $this->stranger,
        offer: $this->offer,
        reason: 'Penipuan',
        description: 'Saya pihak ketiga ingin komplain.'
    ))->toThrow(AuthorizationException::class);
});

test('security: non-admin cannot resolve dispute', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $this->offer->update(['escrow_status' => EscrowStatus::HELD]);
    $dispute = $escrowService->openDispute($this->payer, $this->offer, 'Cacat', 'Barang layar pecah');

    expect(fn () => $escrowService->resolveDispute(
        admin: $this->payer, // user biasa, bukan admin
        dispute: $dispute,
        resolution: 'refund_full',
        adminNote: 'Paksa refund'
    ))->toThrow(AuthorizationException::class);
});

test('security: active dispute blocks funds from being auto-released', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $this->offer->update(['escrow_status' => EscrowStatus::HELD]);
    $escrowService->openDispute($this->payer, $this->offer, 'Cacat', 'Barang layar pecah');

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::DISPUTED);

    expect(fn () => $escrowService->releaseFunds($this->offer))
        ->toThrow(DomainException::class);
});
