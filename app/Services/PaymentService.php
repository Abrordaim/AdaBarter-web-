<?php

namespace App\Services;

use App\Enums\EscrowStatus;
use App\Models\BoostPackage;
use App\Models\Item;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VipPlan;
use App\Models\WebhookLog;
use Illuminate\Auth\Access\AuthorizationException; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Midtrans\Config;
use Midtrans\Snap;
use RuntimeException;

class PaymentService
{
    public function __construct(
        protected EscrowStateMachine $stateMachine,
        protected VoucherService $voucherService
    ) {
        $this->configureMidtrans();
    }

    /**
     * Konfigurasi parameter SDK Midtrans dari config/services.php.
     */
    protected function configureMidtrans(): void
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$clientKey = config('services.midtrans.client_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production', false);
        Config::$isSanitized = (bool) config('services.midtrans.is_sanitized', true);
        Config::$is3ds = (bool) config('services.midtrans.is_3ds', true);
    }

    /**
     * Membuat Snap Token Midtrans untuk pembayaran selisih barter.
     * HANYA boleh dipanggil oleh PAYER transaksi, status WAITING_PAYMENT, metode gateway.
     *
     * @param User $user
     * @param Offer $offer
     * @param int|null $voucherClaimId
     * @return array{token: string, order_id: string, gross_amount: int, original_amount: int, discount_amount: int, redirect_url: string}
     * @throws AuthorizationException
     */
    public function createSnapToken(User $user, Offer $offer, ?int $voucherClaimId = null): array
    {
        // 1. Otorisasi: Hanya payer yang berhak membuat transaksi pembayaran
        if ($offer->payer_id !== $user->id) {
            throw new AuthorizationException('Hanya pihak pembayar selisih (payer) yang berhak membuat pembayaran.');
        }

        // 2. Validasi metode pembayaran
        if (!$offer->isEscrow()) {
            throw new InvalidArgumentException('Transaksi barter ini tidak menggunakan metode pembayaran gateway/escrow.');
        }

        // 3. Validasi status escrow
        if ($offer->escrow_status !== EscrowStatus::WAITING_PAYMENT && $offer->escrow_status !== null) {
            throw new InvalidArgumentException('Status transaksi tidak dalam kondisi menunggu pembayaran (WAITING_PAYMENT).');
        }

        // 4. Pastikan nominal selisih valid dari server (BUKAN dari request client)
        $originalAmount = (int) $offer->price_difference;
        if ($originalAmount <= 0) {
            throw new InvalidArgumentException('Nominal selisih barter harus lebih dari Rp 0.');
        }

        // 5. Validasi dan hitung diskon jika ada voucher yang digunakan
        $discountAmount = 0;
        $grossAmount = $originalAmount;
        $voucherCode = null;

        if (!empty($voucherClaimId)) {
            $voucherData = $this->voucherService->validateVoucherForOffer($user, $voucherClaimId, $offer);
            $discountAmount = $voucherData['discount_amount'];
            $grossAmount = $voucherData['gross_amount'];
            $voucherCode = $voucherData['voucher']->code;
        }

        // 6. Generate Order ID unik dan tidak dapat ditebak
        $orderId = 'BRT-' . $offer->id . '-' . Str::lower(Str::random(8));

        $itemName = 'Selisih Barter #' . $offer->id;
        if ($discountAmount > 0 && $voucherCode) {
            $itemName .= ' (Diskon ' . $voucherCode . ')';
        }

        // 7. Buat parameter transaksi Midtrans Snap
        // URL callback dipakai oleh WebView in-app untuk mendeteksi status pembayaran.
        // URL ini tidak harus aktif secara nyata — WebView mengintersep navigasi ke URL ini
        // sebelum benar-benar di-load, lalu menutup modal dan me-refresh detail penawaran.
        $finishUrl   = 'https://adabarter.payment/finish?order_id=' . $orderId . '&status=success';
        $unfinishUrl = 'https://adabarter.payment/finish?order_id=' . $orderId . '&status=pending';
        $errorUrl    = 'https://adabarter.payment/finish?order_id=' . $orderId . '&status=error';

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?: '',
            ],
            'item_details' => [
                [
                    'id' => 'OFFER-' . $offer->id,
                    'price' => $grossAmount,
                    'quantity' => 1,
                    'name' => mb_substr($itemName, 0, 50),
                ],
            ],
            'callbacks' => [
                'finish'   => $finishUrl,
                'unfinish' => $unfinishUrl,
                'error'    => $errorUrl,
            ],
        ];

        // 8. Buat Snap Token via Midtrans SDK (atau mock instant saat running unit test)
        if (app()->runningUnitTests()) {
            $snapToken = 'mock-snap-token-' . Str::random(24);
        } else {
            try {
                Config::$curlOptions = [];
                $snapToken = Snap::getSnapToken($params);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Midtrans Snap Error: ' . $e->getMessage(), [
                    'order_id' => $orderId,
                    'params'   => $params,
                ]);

                throw new RuntimeException('Gagal membuat transaksi Midtrans: ' . $e->getMessage(), previous: $e);
            }
        }

        // 9. Simpan catatan pembayaran ke tabel payments
        Payment::create([
            'offer_id' => $offer->id,
            'voucher_claim_id' => $voucherClaimId,
            'order_id' => $orderId,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'gross_amount' => $grossAmount,
            'gateway' => 'midtrans',
            'status' => 'pending',
            'snap_token' => $snapToken,
        ]);

        $redirectUrl = config('services.midtrans.is_production')
            ? "https://app.midtrans.com/snap/v2/vtweb/{$snapToken}"
            : "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$snapToken}";

        return [
            'token' => $snapToken,
            'order_id' => $orderId,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'gross_amount' => $grossAmount,
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * Membuat Snap Token Midtrans untuk berlangganan VIP.
     */
    public function createVipSnapToken(User $user, int $planId): array
    {
        $plan = VipPlan::active()->find($planId);
        if (!$plan) {
            throw new InvalidArgumentException('Paket langganan VIP tidak valid atau sudah tidak aktif.');
        }

        $grossAmount = (int) $plan->price;
        $orderId = 'VIP-' . $user->id . '-' . $plan->id . '-' . strtoupper(Str::random(6));

        $finishUrl = 'https://adabarter.payment/vip-finish?order_id=' . $orderId . '&status=success';
        $unfinishUrl = 'https://adabarter.payment/vip-finish?order_id=' . $orderId . '&status=pending';
        $errorUrl = 'https://adabarter.payment/vip-finish?order_id=' . $orderId . '&status=error';

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?: '08123456789',
            ],
            'item_details' => [
                [
                    'id' => 'VIP-' . $plan->id,
                    'price' => $grossAmount,
                    'quantity' => 1,
                    'name' => mb_substr('VIP ' . $plan->name, 0, 50),
                ],
            ],
            'callbacks' => [
                'finish' => $finishUrl,
                'unfinish' => $unfinishUrl,
                'error' => $errorUrl,
            ],
        ];

        if (app()->runningUnitTests()) {
            $snapToken = 'mock-snap-vip-' . Str::random(24);
        } else {
            try {
                Config::$curlOptions = [];
                $snapToken = Snap::getSnapToken($params);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Midtrans Snap VIP Error: ' . $e->getMessage(), [
                    'order_id' => $orderId,
                    'params'   => $params,
                ]);
                throw new RuntimeException('Gagal membuat transaksi Midtrans VIP: ' . $e->getMessage(), previous: $e);
            }
        }

        Payment::create([
            'user_id' => $user->id,
            'type' => 'vip',
            'reference_id' => $plan->id,
            'order_id' => $orderId,
            'original_amount' => $grossAmount,
            'gross_amount' => $grossAmount,
            'gateway' => 'midtrans',
            'status' => 'pending',
            'snap_token' => $snapToken,
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'duration_days' => $plan->duration_days,
                'bonus_post_quota' => $plan->bonus_post_quota,
                'bonus_boost_quota' => $plan->bonus_boost_quota,
            ],
        ]);

        $redirectUrl = config('services.midtrans.is_production')
            ? "https://app.midtrans.com/snap/v2/vtweb/{$snapToken}"
            : "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$snapToken}";

        return [
            'token' => $snapToken,
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'redirect_url' => $redirectUrl,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'duration_days' => $plan->duration_days,
                'price' => (float) $plan->price,
            ],
        ];
    }

    /**
     * Membuat Snap Token Midtrans untuk pembelian iklan sorotan (boost) barang.
     */
    public function createBoostSnapToken(User $user, int $itemId, int $packageId): array
    {
        if ($user->hasRestriction('no_boost')) {
            throw new InvalidArgumentException('Fitur Iklan Sorotan (Boost) sedang dinonaktifkan pada akun Anda karena akumulasi poin penalti.');
        }

        $item = Item::where('id', $itemId)->where('user_id', $user->id)->first();
        if (!$item) {
            throw new AuthorizationException('Barang tidak ditemukan atau bukan milik Anda.');
        }

        $package = BoostPackage::active()->find($packageId);
        if (!$package) {
            throw new InvalidArgumentException('Paket iklan sorotan (boost) tidak valid atau sudah tidak aktif.');
        }

        $grossAmount = (int) $package->price;
        $orderId = 'BST-' . $item->id . '-' . $package->id . '-' . strtoupper(Str::random(6));

        $finishUrl = 'https://adabarter.payment/boost-finish?order_id=' . $orderId . '&status=success';
        $unfinishUrl = 'https://adabarter.payment/boost-finish?order_id=' . $orderId . '&status=pending';
        $errorUrl = 'https://adabarter.payment/boost-finish?order_id=' . $orderId . '&status=error';

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?: '08123456789',
            ],
            'item_details' => [
                [
                    'id' => 'BST-' . $package->id,
                    'price' => $grossAmount,
                    'quantity' => 1,
                    'name' => mb_substr('Boost: ' . $item->title, 0, 50),
                ],
            ],
            'callbacks' => [
                'finish' => $finishUrl,
                'unfinish' => $unfinishUrl,
                'error' => $errorUrl,
            ],
        ];

        if (app()->runningUnitTests()) {
            $snapToken = 'mock-snap-boost-' . Str::random(24);
        } else {
            try {
                Config::$curlOptions = [];
                $snapToken = Snap::getSnapToken($params);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Midtrans Snap Boost Error: ' . $e->getMessage(), [
                    'order_id' => $orderId,
                    'params'   => $params,
                ]);
                throw new RuntimeException('Gagal membuat transaksi Midtrans Boost: ' . $e->getMessage(), previous: $e);
            }
        }

        Payment::create([
            'user_id' => $user->id,
            'type' => 'boost',
            'reference_id' => $item->id,
            'order_id' => $orderId,
            'original_amount' => $grossAmount,
            'gross_amount' => $grossAmount,
            'gateway' => 'midtrans',
            'status' => 'pending',
            'snap_token' => $snapToken,
            'metadata' => [
                'item_id' => $item->id,
                'item_title' => $item->title,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'days' => $package->days,
            ],
        ]);

        $redirectUrl = config('services.midtrans.is_production')
            ? "https://app.midtrans.com/snap/v2/vtweb/{$snapToken}"
            : "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$snapToken}";

        return [
            'token' => $snapToken,
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'redirect_url' => $redirectUrl,
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
            ],
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'days' => $package->days,
                'price' => (float) $package->price,
            ],
        ];
    }

    /**
     * Memproses notifikasi webhook dari Midtrans.
     * Dilindungi verifikasi signature SHA-512, pemeriksaan nominal gross_amount, dan idempoten.
     * Mendukung Escrow (Barter), VIP Subscription, dan Boost Item.
     *
     * @param array $payload
     * @return array{success: bool, message: string, payment: Payment|null}
     * @throws AuthorizationException
     */
    public function handleNotification(array $payload): array
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmountRaw = (string) ($payload['gross_amount'] ?? '');
        $receivedSignature = (string) ($payload['signature_key'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');
        $serverKey = (string) config('services.midtrans.server_key');

        // 1. Verifikasi Signature Key = hash('sha512', order_id . status_code . gross_amount . SERVER_KEY)
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmountRaw . $serverKey);
        $signatureValid = hash_equals($expectedSignature, $receivedSignature);

        // Catat webhook audit log
        WebhookLog::create([
            'gateway' => 'midtrans',
            'payload' => $payload,
            'signature_valid' => $signatureValid,
            'handled_at' => now(),
        ]);

        if (!$signatureValid) {
            throw new AuthorizationException('Signature webhook Midtrans tidak sah / tidak cocok.');
        }

        // 2. Ambil catatan payment
        /** @var Payment|null $payment */
        $payment = Payment::where('order_id', $orderId)->first();

        if (!$payment) {
            throw new RuntimeException("Data pembayaran dengan order_id [{$orderId}] tidak ditemukan.");
        }

        // 3. Cocokkan gross_amount (integer)
        $payloadAmount = (int) round((float) $grossAmountRaw);
        if ($payloadAmount !== (int) $payment->gross_amount) {
            throw new RuntimeException("Nominal pembayaran tidak sesuai. Diharapkan: {$payment->gross_amount}, Diterima: {$payloadAmount}");
        }

        // 4. Idempoten: Jika status sudah settlement dan webhook adalah settlement kedua, abaikan tanpa menggandakan efek
        if ($payment->status === 'settlement' && ($transactionStatus === 'settlement' || $transactionStatus === 'capture')) {
            return [
                'success' => true,
                'message' => 'Notifikasi settlement sudah pernah diproses sebelumnya (idempotent).',
                'payment' => $payment,
            ];
        }

        // 5. Proses perubahan status pembayaran berdasarkan tipe transaksi
        return DB::transaction(function () use ($payment, $payload, $transactionStatus, $fraudStatus) {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->type === 'vip' || str_starts_with($lockedPayment->order_id, 'VIP-')) {
                return $this->handleVipNotification($lockedPayment, $payload, $transactionStatus, $fraudStatus);
            }

            if ($lockedPayment->type === 'boost' || str_starts_with($lockedPayment->order_id, 'BST-')) {
                return $this->handleBoostNotification($lockedPayment, $payload, $transactionStatus, $fraudStatus);
            }

            return $this->handleEscrowNotification($lockedPayment, $payload, $transactionStatus, $fraudStatus);
        });
    }

    /**
     * Menangani notifikasi webhook untuk pembayaran paket VIP.
     */
    protected function handleVipNotification(Payment $lockedPayment, array $payload, string $transactionStatus, string $fraudStatus): array
    {
        if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
            $lockedPayment->update([
                'status' => 'settlement',
                'gateway_transaction_id' => $payload['transaction_id'] ?? null,
                'payment_type' => $payload['payment_type'] ?? null,
                'raw_payload' => $payload,
                'paid_at' => now(),
            ]);

            /** @var User $user */
            $user = User::where('id', $lockedPayment->user_id)->lockForUpdate()->firstOrFail();
            $plan = VipPlan::find($lockedPayment->reference_id);
            $durationDays = $plan ? $plan->duration_days : ($lockedPayment->metadata['duration_days'] ?? 30);

            $currentExpiry = ($user->vip_expires_at && $user->vip_expires_at->isFuture()) ? $user->vip_expires_at : now();
            $newExpiry = $currentExpiry->copy()->addDays($durationDays);

            $user->is_vip = true;
            $user->vip_expires_at = $newExpiry;

            $bonusPost = $plan ? $plan->bonus_post_quota : ($lockedPayment->metadata['bonus_post_quota'] ?? 0);
            $bonusBoost = $plan ? $plan->bonus_boost_quota : ($lockedPayment->metadata['bonus_boost_quota'] ?? 0);

            if ($bonusPost > 0) {
                $user->bonus_post_quota = (int) $user->bonus_post_quota + $bonusPost;
            }
            if ($bonusBoost > 0) {
                $user->boost_quota = (int) $user->boost_quota + $bonusBoost;
            }
            $user->save();

            Subscription::create([
                'user_id' => $user->id,
                'plan' => 'vip',
                'price_paid' => $lockedPayment->gross_amount,
                'started_at' => now(),
                'expired_at' => $newExpiry,
                'is_active' => true,
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'type' => 'subscription',
                'amount' => $lockedPayment->gross_amount,
                'description' => "Langganan VIP ({$plan?->name}) via Midtrans",
                'status' => 'completed',
                'payment_method' => $payload['payment_type'] ?? 'midtrans',
                'payment_ref' => $lockedPayment->order_id,
            ]);

            return [
                'success' => true,
                'message' => 'Pembayaran VIP berhasil dikonfirmasi. Status VIP dan bonus kuota aktif.',
                'payment' => $lockedPayment,
            ];
        }

        if (in_array($transactionStatus, ['expire', 'cancel', 'deny'], true)) {
            $lockedPayment->update([
                'status' => $transactionStatus,
                'gateway_transaction_id' => $payload['transaction_id'] ?? null,
                'raw_payload' => $payload,
            ]);

            return [
                'success' => true,
                'message' => "Pembayaran VIP berstatus [{$transactionStatus}].",
                'payment' => $lockedPayment,
            ];
        }

        $lockedPayment->update([
            'status' => $transactionStatus,
            'gateway_transaction_id' => $payload['transaction_id'] ?? null,
            'raw_payload' => $payload,
        ]);

        return [
            'success' => true,
            'message' => "Status pembayaran VIP diperbarui ke [{$transactionStatus}].",
            'payment' => $lockedPayment,
        ];
    }

    /**
     * Menangani notifikasi webhook untuk pembayaran iklan sorotan (boost) item.
     */
    protected function handleBoostNotification(Payment $lockedPayment, array $payload, string $transactionStatus, string $fraudStatus): array
    {
        if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
            $lockedPayment->update([
                'status' => 'settlement',
                'gateway_transaction_id' => $payload['transaction_id'] ?? null,
                'payment_type' => $payload['payment_type'] ?? null,
                'raw_payload' => $payload,
                'paid_at' => now(),
            ]);

            /** @var Item $item */
            $item = Item::where('id', $lockedPayment->reference_id)->lockForUpdate()->firstOrFail();
            $days = $lockedPayment->metadata['days'] ?? 7;

            $currentExpiry = ($item->boost_expires_at && $item->boost_expires_at->isFuture()) ? $item->boost_expires_at : now();
            $newExpiry = $currentExpiry->copy()->addDays($days);

            $item->update([
                'is_boosted' => true,
                'boost_expires_at' => $newExpiry,
            ]);

            Transaction::create([
                'user_id' => $lockedPayment->user_id,
                'type' => 'boost',
                'amount' => $lockedPayment->gross_amount,
                'description' => "Boost Barang '{$item->title}' ({$days} hari) via Midtrans",
                'status' => 'completed',
                'payment_method' => $payload['payment_type'] ?? 'midtrans',
                'payment_ref' => $lockedPayment->order_id,
            ]);

            return [
                'success' => true,
                'message' => 'Pembayaran Boost berhasil dikonfirmasi. Barang disorot di etalase.',
                'payment' => $lockedPayment,
            ];
        }

        if (in_array($transactionStatus, ['expire', 'cancel', 'deny'], true)) {
            $lockedPayment->update([
                'status' => $transactionStatus,
                'gateway_transaction_id' => $payload['transaction_id'] ?? null,
                'raw_payload' => $payload,
            ]);

            return [
                'success' => true,
                'message' => "Pembayaran Boost berstatus [{$transactionStatus}].",
                'payment' => $lockedPayment,
            ];
        }

        $lockedPayment->update([
            'status' => $transactionStatus,
            'gateway_transaction_id' => $payload['transaction_id'] ?? null,
            'raw_payload' => $payload,
        ]);

        return [
            'success' => true,
            'message' => "Status pembayaran Boost diperbarui ke [{$transactionStatus}].",
            'payment' => $lockedPayment,
        ];
    }

    /**
     * Menangani notifikasi webhook untuk pembayaran selisih transaksi barter (Escrow).
     */
    protected function handleEscrowNotification(Payment $lockedPayment, array $payload, string $transactionStatus, string $fraudStatus): array
    {
        /** @var Offer $offer */
        $offer = Offer::where('id', $lockedPayment->offer_id)->lockForUpdate()->firstOrFail();

        if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
            // Pembayaran sukses
            $lockedPayment->update([
                'status' => 'settlement',
                'gateway_transaction_id' => $payload['transaction_id'] ?? null,
                'payment_type' => $payload['payment_type'] ?? null,
                'raw_payload' => $payload,
                'paid_at' => now(),
            ]);

            // Tandai voucher telah digunakan jika pembayaran berhasil
            if ($lockedPayment->voucher_claim_id) {
                \App\Models\VoucherClaim::where('id', $lockedPayment->voucher_claim_id)->update([
                    'is_used' => true,
                    'used_at' => now(),
                    'offer_id' => $offer->id,
                ]);
            }

            // Transisi escrow: HELD (dana tertahan) -> WAITING_CONFIRMATION (menunggu serah terima)
            $this->stateMachine->transition($offer, EscrowStatus::HELD);

            $deadlineHours = (int) config('services.midtrans.confirm_deadline_hours', 48);
            $lockedPoint = $offer->lockedMeetingPoint;
            $baseTime = ($lockedPoint && $lockedPoint->meet_at && $lockedPoint->meet_at->isFuture())
                ? $lockedPoint->meet_at
                : now();
            $confirmDeadline = $baseTime->copy()->addHours($deadlineHours);

            $this->stateMachine->transition($offer, EscrowStatus::WAITING_CONFIRMATION, [
                'confirm_deadline_at' => $confirmDeadline,
            ]);

            return [
                'success' => true,
                'message' => 'Pembayaran berhasil dikonfirmasi. Dana masuk ke penampungan Escrow.',
                'payment' => $lockedPayment,
            ];
        }

        if (in_array($transactionStatus, ['expire', 'cancel', 'deny'], true)) {
            // Pembayaran gagal atau kedaluwarsa
            $lockedPayment->update([
                'status' => $transactionStatus,
                'gateway_transaction_id' => $payload['transaction_id'] ?? null,
                'raw_payload' => $payload,
            ]);

            // Lepaskan status voucher jika ada
            if ($lockedPayment->voucher_claim_id) {
                \App\Models\VoucherClaim::where('id', $lockedPayment->voucher_claim_id)->update([
                    'is_used' => false,
                    'used_at' => null,
                    'offer_id' => null,
                ]);
            }

            // Jika offer masih WAITING_PAYMENT, tandai EXPIRED
            if ($offer->escrow_status === EscrowStatus::WAITING_PAYMENT) {
                $this->stateMachine->transition($offer, EscrowStatus::EXPIRED);
            }

            return [
                'success' => true,
                'message' => "Pembayaran berstatus [{$transactionStatus}].",
                'payment' => $lockedPayment,
            ];
        }

        // Status pending atau lainnya
        $lockedPayment->update([
            'status' => $transactionStatus,
            'gateway_transaction_id' => $payload['transaction_id'] ?? null,
            'raw_payload' => $payload,
        ]);

        return [
            'success' => true,
            'message' => "Status pembayaran diperbarui ke [{$transactionStatus}].",
            'payment' => $lockedPayment,
        ];
    }
}
