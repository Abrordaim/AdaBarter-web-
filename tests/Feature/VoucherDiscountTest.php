<?php

use App\Enums\EscrowStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Models\Wallet;
use App\Services\EscrowService;
use App\Services\PaymentService;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'is_active' => true,
    ]);

    $this->payer = User::factory()->create([
        'name' => 'Payer User',
        'email' => 'payer@adabarter.com',
    ]);

    $this->receiver = User::factory()->create([
        'name' => 'Receiver User',
        'email' => 'receiver@adabarter.com',
    ]);

    $this->targetItem = Item::create([
        'user_id' => $this->receiver->id,
        'category_id' => $this->category->id,
        'title' => 'iPhone 13 Pro',
        'description' => 'Mulus lengkap',
        'condition' => 'bekas',
        'estimated_price' => 12000000,
        'status' => 'active',
    ]);

    $this->offeredItem = Item::create([
        'user_id' => $this->payer->id,
        'category_id' => $this->category->id,
        'title' => 'iPhone 11',
        'description' => 'Mulus pemakaian',
        'condition' => 'bekas',
        'estimated_price' => 7000000,
        'status' => 'active',
    ]);
});

test('user can fetch available public vouchers with is_claimed status', function () {
    $publicVoucher = Voucher::create([
        'code' => 'PROMOHEMAT50',
        'description' => 'Diskon Rp 50.000',
        'type' => 'fixed',
        'discount_amount' => 50000,
        'min_transaction_amount' => 100000,
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $privateVoucher = Voucher::create([
        'code' => 'SECRETCODE',
        'description' => 'Voucher Rahasia',
        'type' => 'fixed',
        'discount_amount' => 20000,
        'distribution' => 'private',
        'max_claims' => 5,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/vouchers/available');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['code'])->toBe('PROMOHEMAT50');
    expect($data[0]['is_claimed'])->toBeFalse();
    expect($data[0]['formatted_discount'])->toBe('Rp 50.000');
});

test('user can claim public voucher via voucher_id (Metode 2: 1-Tap)', function () {
    $voucher = Voucher::create([
        'code' => 'TAPCLAIM25',
        'description' => 'Diskon 25rb 1-tap',
        'type' => 'fixed',
        'discount_amount' => 25000,
        'distribution' => 'public',
        'max_claims' => 5,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/vouchers/claim', [
            'voucher_id' => $voucher->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher.code', 'TAPCLAIM25');

    expect(VoucherClaim::where('user_id', $this->payer->id)->where('voucher_id', $voucher->id)->exists())->toBeTrue();
    expect($voucher->fresh()->claimed_count)->toBe(1);

    // List available now shows is_claimed = true
    $availableRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/vouchers/available');
    expect($availableRes->json('data.0.is_claimed'))->toBeTrue();
});

test('user can claim private voucher via redeem code (Metode 1: Kode Promo)', function () {
    $voucher = Voucher::create([
        'code' => 'INFLUENCER100',
        'description' => 'Kode promo khusus dari influencer',
        'type' => 'fixed',
        'discount_amount' => 100000,
        'distribution' => 'private',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/vouchers/claim', [
            'code' => 'INFLUENCER100',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher.code', 'INFLUENCER100');

    expect(VoucherClaim::where('user_id', $this->payer->id)->where('voucher_id', $voucher->id)->exists())->toBeTrue();
});

test('user cannot claim private voucher via voucher_id directly', function () {
    $voucher = Voucher::create([
        'code' => 'SECRET99',
        'type' => 'fixed',
        'discount_amount' => 99000,
        'distribution' => 'private',
        'max_claims' => 5,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/vouchers/claim', [
            'voucher_id' => $voucher->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['voucher_id']);
});

test('user cannot claim the same voucher twice', function () {
    $voucher = Voucher::create([
        'code' => 'DOUBLECLAIM',
        'type' => 'fixed',
        'discount_amount' => 50000,
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/vouchers/claim', ['voucher_id' => $voucher->id])
        ->assertStatus(200);

    $secondClaim = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/vouchers/claim', ['voucher_id' => $voucher->id]);

    $secondClaim->assertStatus(422);
});

test('user can view their claimed vouchers in getMyVouchers', function () {
    $voucher = Voucher::create([
        'code' => 'MYVOUCHER',
        'type' => 'percentage',
        'percentage' => 15,
        'max_discount_amount' => 75000,
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/vouchers/claim', ['voucher_id' => $voucher->id]);

    $myRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/vouchers/my');

    $myRes->assertStatus(200);
    $myVouchers = $myRes->json('data');
    expect(count($myVouchers))->toBe(1);
    expect($myVouchers[0]['voucher']['code'])->toBe('MYVOUCHER');
    expect($myVouchers[0]['voucher']['formatted_discount'])->toBe('15% (Maks. Rp 75.000)');
    expect($myVouchers[0]['is_used'])->toBeFalse();
});

test('payer can pay escrow with a fixed discount voucher and receive discounted snap token', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->offeredItem->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->targetItem->id,
        'status' => 'matched',
        'payment_method' => 'gateway',
        'price_difference' => 500000,
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::WAITING_PAYMENT,
    ]);

    $voucher = Voucher::create([
        'code' => 'POTONG50K',
        'type' => 'fixed',
        'discount_amount' => 50000,
        'min_transaction_amount' => 200000,
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $claim = VoucherClaim::create([
        'user_id' => $this->payer->id,
        'voucher_id' => $voucher->id,
        'claimed_at' => now(),
        'is_used' => false,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $payRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/offers/{$offer->id}/pay", [
            'voucher_claim_id' => $claim->id,
        ]);

    $payRes->assertStatus(200)
        ->assertJsonPath('data.original_amount', 500000)
        ->assertJsonPath('data.discount_amount', 50000)
        ->assertJsonPath('data.gross_amount', 450000);

    $payment = Payment::where('offer_id', $offer->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->gross_amount)->toBe(450000);
    expect($payment->discount_amount)->toBe(50000);
    expect($payment->original_amount)->toBe(500000);
    expect($payment->voucher_claim_id)->toBe($claim->id);
});

test('payer can pay escrow with a percentage discount voucher capped by max_discount_amount', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->offeredItem->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->targetItem->id,
        'status' => 'matched',
        'payment_method' => 'gateway',
        'price_difference' => 1000000, // 1jt
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::WAITING_PAYMENT,
    ]);

    // 20% of 1jt = 200rb, but max is 80rb
    $voucher = Voucher::create([
        'code' => 'DISC20PCT',
        'type' => 'percentage',
        'percentage' => 20,
        'max_discount_amount' => 80000,
        'min_transaction_amount' => 200000,
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $claim = VoucherClaim::create([
        'user_id' => $this->payer->id,
        'voucher_id' => $voucher->id,
        'claimed_at' => now(),
        'is_used' => false,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $payRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/offers/{$offer->id}/pay", [
            'voucher_claim_id' => $claim->id,
        ]);

    $payRes->assertStatus(200)
        ->assertJsonPath('data.original_amount', 1000000)
        ->assertJsonPath('data.discount_amount', 80000)
        ->assertJsonPath('data.gross_amount', 920000);
});

test('voucher rejected if offer price_difference is below min_transaction_amount', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->offeredItem->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->targetItem->id,
        'status' => 'matched',
        'payment_method' => 'gateway',
        'price_difference' => 150000, // below 200rb min
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::WAITING_PAYMENT,
    ]);

    $voucher = Voucher::create([
        'code' => 'MIN200K',
        'type' => 'fixed',
        'discount_amount' => 50000,
        'min_transaction_amount' => 200000,
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $claim = VoucherClaim::create([
        'user_id' => $this->payer->id,
        'voucher_id' => $voucher->id,
        'claimed_at' => now(),
        'is_used' => false,
    ]);

    $token = $this->payer->createToken('test')->plainTextToken;

    $payRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/offers/{$offer->id}/pay", [
            'voucher_claim_id' => $claim->id,
        ]);

    $payRes->assertStatus(400);
});

test('webhook settlement marks voucher_claim as used and webhook cancel releases it', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->offeredItem->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->targetItem->id,
        'status' => 'matched',
        'payment_method' => 'gateway',
        'price_difference' => 300000,
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::WAITING_PAYMENT,
    ]);

    $voucher = Voucher::create([
        'code' => 'VOUCHER30K',
        'type' => 'fixed',
        'discount_amount' => 30000,
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $claim = VoucherClaim::create([
        'user_id' => $this->payer->id,
        'voucher_id' => $voucher->id,
        'claimed_at' => now(),
        'is_used' => false,
    ]);

    $paymentService = app(PaymentService::class);
    $result = $paymentService->createSnapToken($this->payer, $offer, $claim->id);

    $serverKey = (string) config('services.midtrans.server_key');
    $orderId = $result['order_id'];
    $grossAmountStr = '270000.00';
    $statusCode = '200';
    $sig = hash('sha512', $orderId . $statusCode . $grossAmountStr . $serverKey);

    $webhookRes = $this->postJson('/api/payments/midtrans/notification', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmountStr,
        'signature_key' => $sig,
        'transaction_status' => 'settlement',
        'transaction_id' => 'trx-12345',
        'fraud_status' => 'accept',
        'payment_type' => 'gopay',
    ]);

    $webhookRes->assertStatus(200);

    $claim->refresh();
    expect($claim->is_used)->toBeTrue();
    expect($claim->offer_id)->toBe($offer->id);
    expect($claim->used_at)->not->toBeNull();
});

test('platform subsidy model: receiver receives full price_difference in wallet upon escrow release', function () {
    $offer = Offer::create([
        'offerer_user_id' => $this->payer->id,
        'offerer_item_id' => $this->offeredItem->id,
        'target_user_id' => $this->receiver->id,
        'target_item_id' => $this->targetItem->id,
        'status' => 'matched',
        'payment_method' => 'gateway',
        'price_difference' => 500000, // 500rb
        'payer_id' => $this->payer->id,
        'receiver_id' => $this->receiver->id,
        'escrow_status' => EscrowStatus::WAITING_PAYMENT,
    ]);

    $voucher = Voucher::create([
        'code' => 'SUBSIDI100',
        'type' => 'fixed',
        'discount_amount' => 100000, // Platform subsidizes 100rb discount
        'distribution' => 'public',
        'max_claims' => 10,
        'claimed_count' => 0,
        'is_active' => true,
    ]);

    $claim = VoucherClaim::create([
        'user_id' => $this->payer->id,
        'voucher_id' => $voucher->id,
        'claimed_at' => now(),
        'is_used' => false,
    ]);

    $paymentService = app(PaymentService::class);
    // Payer only pays 400.000 via gateway
    $tokenRes = $paymentService->createSnapToken($this->payer, $offer, $claim->id);
    expect($tokenRes['gross_amount'])->toBe(400000);

    // Simulate payment settlement -> Escrow goes to WAITING_CONFIRMATION
    $serverKey = (string) config('services.midtrans.server_key');
    $orderId = $tokenRes['order_id'];
    $sig = hash('sha512', $orderId . '200' . '400000.00' . $serverKey);
    $paymentService->handleNotification([
        'order_id' => $orderId,
        'status_code' => '200',
        'gross_amount' => '400000.00',
        'signature_key' => $sig,
        'transaction_status' => 'settlement',
        'transaction_id' => 'trx-subsidi-test',
        'fraud_status' => 'accept',
    ]);

    $offer->refresh();
    expect($offer->escrow_status)->toBe(EscrowStatus::WAITING_CONFIRMATION);

    // Now release funds to receiver
    $escrowService = app(EscrowService::class);
    $escrowService->releaseFunds($offer);

    // Receiver must have received the FULL 500.000 in wallet
    $receiverWallet = Wallet::where('user_id', $this->receiver->id)->first();
    expect($receiverWallet)->not->toBeNull();
    expect($receiverWallet->balance)->toBe(500000); // 100% full amount subsidized!
});
