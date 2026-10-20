<?php

use App\Enums\EscrowStatus;
use App\Livewire\Admin\DisputeManagement;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use App\Services\EscrowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'laptop',
    ]);

    $this->payer = User::factory()->create(['name' => 'Payer Andi', 'role' => 'user']);
    $this->receiver = User::factory()->create(['name' => 'Receiver Budi', 'role' => 'user']);
    $this->admin = User::factory()->create(['name' => 'Admin Super', 'role' => 'admin']);
    $this->stranger = User::factory()->create(['name' => 'Stranger Cici', 'role' => 'user']);

    $this->itemPayer = Item::create([
        'user_id' => $this->payer->id,
        'category_id' => $this->category->id,
        'title' => 'iPad Mini 4',
        'description' => 'iPad mulus',
        'condition' => 'bekas_baik',
        'estimated_price' => 2000000,
        'status' => 'active',
    ]);

    $this->itemReceiver = Item::create([
        'user_id' => $this->receiver->id,
        'category_id' => $this->category->id,
        'title' => 'iPhone 11',
        'description' => 'iPhone mulus lengkap',
        'condition' => 'bekas_baik',
        'estimated_price' => 3000000,
        'status' => 'active',
    ]);

    $this->offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->itemPayer->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->itemReceiver->id,
        'payment_method' => 'gateway',
        'price_difference' => 1000000,
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::HELD,
        'handover_code' => '112233',
        'handover_attempts' => 0,
        'status' => 'matched',
        'offerer_approved' => true,
        'target_approved' => true,
    ]);
});

test('participant (payer) can open dispute when offer escrow_status is HELD', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $dispute = $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Barang tidak sesuai kondisi fisik',
        description: 'Layar retak halus dan baterai drop parah saat cek fisik bersama.',
        evidencePaths: ['disputes/sample.jpg']
    );

    expect($dispute)->toBeInstanceOf(Dispute::class)
        ->and($dispute->status)->toBe('open')
        ->and($dispute->reason)->toBe('Barang tidak sesuai kondisi fisik')
        ->and($dispute->reported_by)->toBe($this->payer->id)
        ->and($dispute->evidence_paths)->toEqual(['disputes/sample.jpg']);

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::DISPUTED);
});

test('participant (receiver) can open dispute when offer escrow_status is WAITING_CONFIRMATION', function () {
    $this->offer->update(['escrow_status' => EscrowStatus::WAITING_CONFIRMATION]);

    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $dispute = $escrowService->openDispute(
        user: $this->receiver,
        offer: $this->offer,
        reason: 'Pihak pembayar membatalkan sepihak di lokasi',
        description: 'Pembayar tidak bersedia menyerahkan barang miliknya.',
    );

    expect($dispute->status)->toBe('open')
        ->and($dispute->reported_by)->toBe($this->receiver->id);

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::DISPUTED);
});

test('stranger cannot open dispute (AuthorizationException)', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    expect(fn () => $escrowService->openDispute(
        user: $this->stranger,
        offer: $this->offer,
        reason: 'Usil',
        description: 'Bukan urusan saya'
    ))->toThrow(AuthorizationException::class);
});

test('cannot open dispute on ineligible escrow status like WAITING_PAYMENT or COMPLETED', function () {
    $this->offer->update(['escrow_status' => EscrowStatus::WAITING_PAYMENT]);

    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    expect(fn () => $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Belum bayar tapi komplain',
        description: 'Harusnya tidak bisa'
    ))->toThrow(DomainException::class);
});

test('cannot open multiple open disputes on the same offer', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Alasan 1',
        description: 'Deskripsi 1'
    );

    expect(fn () => $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Alasan 2',
        description: 'Deskripsi 2'
    ))->toThrow(DomainException::class);
});

test('admin can resolve dispute with refund_full (payer wallet credited full, offer cancelled, items active)', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $dispute = $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Barang rusak total',
        description: 'Barang mati total saat dicoba di lokasi.'
    );

    $resolved = $escrowService->resolveDispute(
        admin: $this->admin,
        dispute: $dispute,
        resolution: 'refund_full',
        adminNote: 'Barang terbukti rusak saat pengetesan langsung.'
    );

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->resolution)->toBe('refund_full')
        ->and($resolved->refund_amount)->toBe(1000000)
        ->and($resolved->resolved_by)->toBe($this->admin->id);

    // Dompet payer bertambah 1 juta
    $payerWallet = $this->payer->getOrCreateWallet();
    expect($payerWallet->balance)->toBe(1000000);

    // Tawaran dibatalkan dan status escrow REFUNDED
    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::REFUNDED)
        ->and($this->offer->status)->toBe('cancelled');

    // Status barang kembali active
    $this->itemPayer->refresh();
    $this->itemReceiver->refresh();
    expect($this->itemPayer->status)->toBe('active')
        ->and($this->itemReceiver->status)->toBe('active');
});

test('admin can resolve dispute with release (receiver wallet credited, offer completed, items traded)', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $dispute = $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Komplain tidak berdasar',
        description: 'Mengaku lecet padahal sudah dijelaskan di deskripsi.'
    );

    $resolved = $escrowService->resolveDispute(
        admin: $this->admin,
        dispute: $dispute,
        resolution: 'release',
        adminNote: 'Kondisi barang telah sesuai dengan deskripsi awal.'
    );

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->resolution)->toBe('release')
        ->and($resolved->refund_amount)->toBe(0);

    // Dompet receiver bertambah 1 juta (net tanpa fee)
    $receiverWallet = $this->receiver->getOrCreateWallet();
    expect($receiverWallet->balance)->toBe(1000000);

    // Tawaran selesai dan status escrow COMPLETED
    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::COMPLETED)
        ->and($this->offer->status)->toBe('completed');

    // Status barang menjadi traded
    $this->itemPayer->refresh();
    $this->itemReceiver->refresh();
    expect($this->itemPayer->status)->toBe('traded')
        ->and($this->itemReceiver->status)->toBe('traded');
});

test('admin can resolve dispute with refund_partial', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $dispute = $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Charger tidak ori',
        description: 'Charger pengganti bukan original Apple.'
    );

    // Split: 300rb refund ke Payer, 700rb release ke Receiver
    $resolved = $escrowService->resolveDispute(
        admin: $this->admin,
        dispute: $dispute,
        resolution: 'refund_partial',
        refundAmount: 300000,
        adminNote: 'Disepakati kompensasi adaptor charger 300rb ke pembayar.'
    );

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->resolution)->toBe('refund_partial')
        ->and($resolved->refund_amount)->toBe(300000);

    // Dompet Payer dapat 300.000
    expect($this->payer->getOrCreateWallet()->balance)->toBe(300000);

    // Dompet Receiver dapat 700.000
    expect($this->receiver->getOrCreateWallet()->balance)->toBe(700000);

    // Escrow COMPLETED
    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::COMPLETED)
        ->and($this->offer->status)->toBe('completed');
});

test('non-admin user cannot resolve dispute (AuthorizationException)', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $dispute = $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Alasan',
        description: 'Deskripsi keluhan'
    );

    expect(fn () => $escrowService->resolveDispute(
        admin: $this->stranger,
        dispute: $dispute,
        resolution: 'refund_full'
    ))->toThrow(AuthorizationException::class);
});

test('API POST /api/offers/{id}/dispute opens dispute with photo evidence', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('bukti_retak.jpg');

    $response = $this->actingAs($this->payer, 'sanctum')->postJson("/api/offers/{$this->offer->id}/dispute", [
        'reason' => 'Kondisi barang tidak sesuai',
        'description' => 'Terdapat retak pada frame saat serah terima.',
        'evidence' => [$file],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.dispute.reason', 'Kondisi barang tidak sesuai')
        ->assertJsonPath('data.dispute.status', 'open');

    $this->assertDatabaseHas('disputes', [
        'offer_id' => $this->offer->id,
        'reported_by' => $this->payer->id,
        'status' => 'open',
    ]);

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::DISPUTED);
});

test('Livewire Admin DisputeManagement component mounts, filters and resolves dispute', function () {
    /** @var EscrowService $escrowService */
    $escrowService = app(EscrowService::class);

    $dispute = $escrowService->openDispute(
        user: $this->payer,
        offer: $this->offer,
        reason: 'Layar bergaris',
        description: 'Layar ada garis hijau vertikal.'
    );

    Livewire::actingAs($this->admin)
        ->test(DisputeManagement::class)
        ->assertSee('Sengketa Transaksi Escrow')
        ->assertSee('#DISP-' . $dispute->id)
        ->call('viewDispute', $dispute->id)
        ->assertSet('selectedDispute.id', $dispute->id)
        ->set('resolutionType', 'refund_full')
        ->set('adminNote', 'Disetujui refund penuh karena cacat panel layar.')
        ->call('resolve')
        ->assertDispatched('notify');

    $dispute->refresh();
    expect($dispute->status)->toBe('resolved')
        ->and($dispute->resolution)->toBe('refund_full');
});
