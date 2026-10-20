<?php

use App\Enums\EscrowStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\PaymentService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    config()->set('services.midtrans.server_key', 'test-server-key-12345');
    config()->set('services.midtrans.confirm_deadline_hours', 48);

    $this->category = Category::create([
        'name' => 'Gadget',
        'slug' => 'gadget',
        'icon' => 'phone',
    ]);

    $this->payer = User::factory()->create(['name' => 'Payer Budi']);
    $this->receiver = User::factory()->create(['name' => 'Receiver Siti']);
    $this->stranger = User::factory()->create(['name' => 'Stranger']);

    $this->itemPayer = Item::create([
        'user_id' => $this->payer->id,
        'category_id' => $this->category->id,
        'title' => 'HP Lama (1 Juta)',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 1000000,
        'status' => 'active',
    ]);

    $this->itemReceiver = Item::create([
        'user_id' => $this->receiver->id,
        'category_id' => $this->category->id,
        'title' => 'Laptop Baru (1.5 Juta)',
        'description' => 'Desc',
        'condition' => 'bekas_baik',
        'estimated_price' => 1500000,
        'status' => 'active',
    ]);

    // Offer with price difference 500.000 where payer is Payer Budi
    $this->offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->itemPayer->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->itemReceiver->id,
        'payment_method' => 'gateway',
        'price_difference' => 500000,
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::WAITING_PAYMENT,
        'status' => 'matched',
        'offerer_approved' => true,
        'target_approved' => true,
    ]);
});

test('only payer can generate snap token; stranger or receiver is forbidden', function () {
    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);

    // Stranger tries to generate payment token -> 403 Forbidden
    expect(fn () => $paymentService->createSnapToken($this->stranger, $this->offer))
        ->toThrow(AuthorizationException::class);

    // Receiver tries to generate payment token -> 403 Forbidden
    expect(fn () => $paymentService->createSnapToken($this->receiver, $this->offer))
        ->toThrow(AuthorizationException::class);

    // Payer successfully creates snap token
    $res = $paymentService->createSnapToken($this->payer, $this->offer);

    expect($res)->toHaveKeys(['token', 'order_id', 'gross_amount', 'redirect_url'])
        ->and($res['gross_amount'])->toBe(500000)
        ->and($res['order_id'])->toStartWith("BRT-{$this->offer->id}-");

    // Check payment record saved in DB
    $this->assertDatabaseHas('payments', [
        'offer_id' => $this->offer->id,
        'order_id' => $res['order_id'],
        'gross_amount' => 500000,
        'status' => 'pending',
    ]);
});

test('api endpoint /api/offers/{id}/pay generates snap token for payer', function () {
    $token = $this->payer->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/offers/{$this->offer->id}/pay");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.gross_amount', 500000);

    expect($response->json('data.order_id'))->toStartWith("BRT-{$this->offer->id}-");
});

test('webhook rejects notification with invalid signature key with 403', function () {
    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $paymentData = $paymentService->createSnapToken($this->payer, $this->offer);

    $invalidPayload = [
        'order_id' => $paymentData['order_id'],
        'status_code' => '200',
        'gross_amount' => '500000.00',
        'signature_key' => 'totally-wrong-fake-signature',
        'transaction_status' => 'settlement',
    ];

    $response = $this->postJson('/api/payments/midtrans/notification', $invalidPayload);

    $response->assertStatus(403)
        ->assertJsonPath('success', false);

    // Webhook log records invalid signature
    $this->assertDatabaseHas('webhook_logs', [
        'signature_valid' => false,
    ]);
});

test('webhook rejects notification with gross amount mismatch', function () {
    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $paymentData = $paymentService->createSnapToken($this->payer, $this->offer);

    // Payload claims gross_amount is 100.000 instead of 500.000
    $tamperedAmount = '100000.00';
    $serverKey = config('services.midtrans.server_key');
    $validSignatureForTamperedAmount = hash('sha512', $paymentData['order_id'] . '200' . $tamperedAmount . $serverKey);

    $payload = [
        'order_id' => $paymentData['order_id'],
        'status_code' => '200',
        'gross_amount' => $tamperedAmount,
        'signature_key' => $validSignatureForTamperedAmount,
        'transaction_status' => 'settlement',
    ];

    $response = $this->postJson('/api/payments/midtrans/notification', $payload);

    $response->assertStatus(400)
        ->assertJsonPath('success', false);
});

test('valid settlement webhook transitions escrow to HELD then WAITING_CONFIRMATION with deadline', function () {
    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $paymentData = $paymentService->createSnapToken($this->payer, $this->offer);

    $serverKey = config('services.midtrans.server_key');
    $orderId = $paymentData['order_id'];
    $grossAmount = '500000.00';
    $statusCode = '200';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
        'transaction_id' => 'midtrans-trx-987654',
        'payment_type' => 'qris',
    ];

    $response = $this->postJson('/api/payments/midtrans/notification', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    // Check payment status is settlement
    $this->assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'status' => 'settlement',
        'gateway_transaction_id' => 'midtrans-trx-987654',
        'payment_type' => 'qris',
    ]);

    // Check offer escrow_status is WAITING_CONFIRMATION, handover_code is set, and confirm_deadline_at is set
    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION)
        ->and($this->offer->handover_code)->not->toBeNull()
        ->and($this->offer->confirm_deadline_at)->not->toBeNull();

    // Verify webhook log is recorded with signature_valid true
    $this->assertDatabaseHas('webhook_logs', [
        'signature_valid' => true,
    ]);
});

test('settlement webhook is idempotent: duplicate notification does not cause duplicate effects', function () {
    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $paymentData = $paymentService->createSnapToken($this->payer, $this->offer);

    $serverKey = config('services.midtrans.server_key');
    $orderId = $paymentData['order_id'];
    $grossAmount = '500000.00';
    $statusCode = '200';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
        'transaction_id' => 'midtrans-trx-987654',
    ];

    // First call
    $res1 = $this->postJson('/api/payments/midtrans/notification', $payload);
    $res1->assertStatus(200);

    // Second duplicate call
    $res2 = $this->postJson('/api/payments/midtrans/notification', $payload);
    $res2->assertStatus(200);

    // Status remains WAITING_CONFIRMATION, only 1 payment row exists
    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION);
    expect(Payment::where('order_id', $orderId)->count())->toBe(1);
});

test('expire or cancel webhook marks payment failed and escrow EXPIRED', function () {
    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $paymentData = $paymentService->createSnapToken($this->payer, $this->offer);

    $serverKey = config('services.midtrans.server_key');
    $orderId = $paymentData['order_id'];
    $grossAmount = '500000.00';
    $statusCode = '202';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'expire',
        'transaction_id' => 'midtrans-trx-expired',
    ];

    $response = $this->postJson('/api/payments/midtrans/notification', $payload);
    $response->assertStatus(200);

    $this->assertDatabaseHas('payments', [
        'order_id' => $orderId,
        'status' => 'expire',
    ]);

    $this->offer->refresh();
    expect($this->offer->escrow_status)->toBe(EscrowStatus::EXPIRED);
});
