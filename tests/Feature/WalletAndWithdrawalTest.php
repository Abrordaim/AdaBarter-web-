<?php

use App\Livewire\Admin\WithdrawalManagement;
use App\Models\Category;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Pengguna Tes', 'role' => 'user']);
    $this->admin = User::factory()->create(['name' => 'Admin Tes', 'role' => 'admin']);

    // Fund user wallet with 500k
    $this->wallet = $this->user->getOrCreateWallet();
    $this->wallet->credit(500_000, 'test_credit');
});

// ─── WalletService Tests ──────────────────────────────────────────────────────

test('user can see wallet balance and transaction history via API', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/wallet');

    $response->assertStatus(200)
        ->assertJsonPath('data.balance', 500_000)
        ->assertJsonStructure(['data' => ['balance', 'transactions']]);
});

test('user can request withdrawal and saldo didebit segera', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);

    $withdrawal = $svc->requestWithdrawal(
        user: $this->user,
        amount: 200_000,
        bankName: 'BCA',
        accountNumber: '1234567890',
        accountName: 'Pengguna Tes',
    );

    expect($withdrawal->status)->toBe('pending')
        ->and($withdrawal->amount)->toBe(200_000);

    // Saldo harus berkurang langsung
    $this->wallet->refresh();
    expect($this->wallet->balance)->toBe(300_000);
});

test('withdrawal request below minimum is rejected (DomainException)', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);

    expect(fn () => $svc->requestWithdrawal(
        user: $this->user,
        amount: 10_000,
        bankName: 'Mandiri',
        accountNumber: '9999',
        accountName: 'Test',
    ))->toThrow(DomainException::class);
});

test('withdrawal request exceeding balance is rejected (RuntimeException)', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);

    expect(fn () => $svc->requestWithdrawal(
        user: $this->user,
        amount: 999_999,
        bankName: 'BRI',
        accountNumber: '5555',
        accountName: 'Test Besar',
    ))->toThrow(RuntimeException::class);
});

test('admin can approve withdrawal (status becomes paid)', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);

    $withdrawal = $svc->requestWithdrawal($this->user, 100_000, 'BNI', '7777', 'Pengguna Tes');

    $approved = $svc->approveWithdrawal($this->admin, $withdrawal);

    expect($approved->status)->toBe('paid')
        ->and($approved->processed_by)->toBe($this->admin->id);
});

test('admin can reject withdrawal and saldo dikembalikan', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);

    $withdrawal = $svc->requestWithdrawal($this->user, 150_000, 'BSI', '3333', 'Pengguna Tes');

    // Saldo setelah debit = 350k
    $this->wallet->refresh();
    expect($this->wallet->balance)->toBe(350_000);

    $svc->rejectWithdrawal($this->admin, $withdrawal, 'Nomor rekening tidak valid');

    // Saldo kembali ke 500k
    $this->wallet->refresh();
    expect($this->wallet->balance)->toBe(500_000);

    $withdrawal->refresh();
    expect($withdrawal->status)->toBe('rejected')
        ->and($withdrawal->rejection_reason)->toBe('Nomor rekening tidak valid');
});

test('non-admin cannot approve withdrawal (AuthorizationException)', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);

    $withdrawal = $svc->requestWithdrawal($this->user, 50_000, 'Mandiri', '8888', 'Test');

    expect(fn () => $svc->approveWithdrawal($this->user, $withdrawal))
        ->toThrow(AuthorizationException::class);
});

// ─── API Endpoint Tests ───────────────────────────────────────────────────────

test('POST /api/wallet/withdraw creates withdrawal via API', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/wallet/withdraw', [
        'amount'         => 200_000,
        'bank_name'      => 'CIMB',
        'account_number' => '4321',
        'account_name'   => 'Pengguna Tes',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.withdrawal.status', 'pending')
        ->assertJsonPath('data.withdrawal.amount', 200_000);

    $this->assertDatabaseHas('withdrawals', [
        'user_id' => $this->user->id,
        'status'  => 'pending',
        'amount'  => 200_000,
    ]);
});

test('GET /api/wallet/withdrawals returns list of user withdrawals', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);

    $svc->requestWithdrawal($this->user, 50_000, 'BCA', '1111', 'Test');
    $svc->requestWithdrawal($this->user, 75_000, 'BNI', '2222', 'Test');

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/wallet/withdrawals');

    $response->assertStatus(200)->assertJsonPath('success', true);
    expect($response->json('data.data'))->toHaveCount(2);
});

// ─── Livewire Admin Component Tests ──────────────────────────────────────────

test('Livewire WithdrawalManagement shows pending withdrawals and admin can approve', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);
    $withdrawal = $svc->requestWithdrawal($this->user, 100_000, 'Mandiri', '0101', 'Pengguna Tes');

    Livewire::actingAs($this->admin)
        ->test(WithdrawalManagement::class)
        ->assertSee('Penarikan Dana Pengguna')
        ->assertSee('Mandiri')
        ->call('viewWithdrawal', $withdrawal->id)
        ->assertSet('selectedWithdrawal.id', $withdrawal->id)
        ->call('approve')
        ->assertDispatched('notify');

    $withdrawal->refresh();
    expect($withdrawal->status)->toBe('paid');
});

test('Livewire WithdrawalManagement admin can reject withdrawal', function () {
    /** @var WalletService $svc */
    $svc = app(WalletService::class);
    $withdrawal = $svc->requestWithdrawal($this->user, 50_000, 'BRI', '9090', 'Pengguna Tes');

    Livewire::actingAs($this->admin)
        ->test(WithdrawalManagement::class)
        ->call('viewWithdrawal', $withdrawal->id)
        ->set('rejectionReason', 'Nama rekening tidak sesuai KTP.')
        ->call('reject')
        ->assertDispatched('notify');

    $withdrawal->refresh();
    expect($withdrawal->status)->toBe('rejected');

    // Saldo dikembalikan
    $this->wallet->refresh();
    expect($this->wallet->balance)->toBe(500_000);
});
