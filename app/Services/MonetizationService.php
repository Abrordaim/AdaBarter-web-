<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonetizationService
{
    /**
     * Get available VIP subscription plans.
     */
    public function getSubscriptionPlans(): array
    {
        return [
            [
                'id' => 'vip_1m',
                'name' => 'VIP 1 Bulan',
                'duration_days' => 30,
                'price' => 49000,
                'formatted_price' => 'Rp 49.000',
                'tag' => 'Standar',
                'features' => [
                    'Posting barang tanpa batas (Unlimited Quota)',
                    'Lencana VIP eksklusif di profil & katalog',
                    'Prioritas tampil di hasil pencarian',
                    'Dukungan bantuan prioritas',
                ],
            ],
            [
                'id' => 'vip_3m',
                'name' => 'VIP 3 Bulan',
                'duration_days' => 90,
                'price' => 119000,
                'formatted_price' => 'Rp 119.000',
                'tag' => 'Paling Populer',
                'badge' => 'Hemat 20%',
                'features' => [
                    'Semua keuntungan VIP 1 Bulan',
                    'Masa aktif lebih panjang (90 hari)',
                    'Hemat biaya langganan bulanan',
                ],
            ],
            [
                'id' => 'vip_1y',
                'name' => 'VIP 1 Tahun',
                'duration_days' => 365,
                'price' => 399000,
                'formatted_price' => 'Rp 399.000',
                'tag' => 'Terbaik',
                'badge' => 'Hemat 35%',
                'features' => [
                    'Semua keuntungan paket VIP',
                    'Bebas posting selama 365 hari penuh',
                    'Bonus 2x Free Boost Listing mingguan',
                ],
            ],
        ];
    }

    /**
     * Get available Item Boost packages.
     */
    public function getBoostPackages(): array
    {
        return [
            [
                'id' => 'boost_3d',
                'days' => 3,
                'price' => 15000,
                'formatted_price' => 'Rp 15.000',
                'label' => 'Boost 3 Hari',
                'description' => 'Posisi teratas feed beranda selama 3 hari',
            ],
            [
                'id' => 'boost_7d',
                'days' => 7,
                'price' => 29000,
                'formatted_price' => 'Rp 29.000',
                'label' => 'Boost 7 Hari',
                'tag' => 'Paling Diminati',
                'description' => 'Maksimal eksposur barter selama 1 minggu penuh',
            ],
            [
                'id' => 'boost_30d',
                'days' => 30,
                'price' => 89000,
                'formatted_price' => 'Rp 89.000',
                'label' => 'Boost 30 Hari',
                'description' => 'Prioritas sorotan selama 1 bulan',
            ],
        ];
    }

    /**
     * Get pay-per-post quota packages.
     */
    public function getQuotaPackages(): array
    {
        return [
            [
                'id' => 'quota_1',
                'slots' => 1,
                'price' => 10000,
                'formatted_price' => 'Rp 10.000',
                'label' => '+1 Slot Barang',
            ],
            [
                'id' => 'quota_3',
                'slots' => 3,
                'price' => 25000,
                'formatted_price' => 'Rp 25.000',
                'label' => '+3 Slot Barang',
                'badge' => 'Hemat Rp 5.000',
            ],
            [
                'id' => 'quota_5',
                'slots' => 5,
                'price' => 40000,
                'formatted_price' => 'Rp 40.000',
                'label' => '+5 Slot Barang',
                'badge' => 'Hemat Rp 10.000',
            ],
        ];
    }

    /**
     * Subscribe user to a VIP plan (Localhost simulation).
     */
    public function subscribe(User $user, string $planId, string $paymentMethod = 'QRIS / Bank Transfer'): array
    {
        $plans = collect($this->getSubscriptionPlans());
        $selectedPlan = $plans->firstWhere('id', $planId);

        if (!$selectedPlan) {
            throw ValidationException::withMessages(['plan' => 'Paket langganan tidak valid.']);
        }

        return DB::transaction(function () use ($user, $selectedPlan, $paymentMethod) {
            $durationDays = $selectedPlan['duration_days'];
            $startedAt = now();
            $expiredAt = now()->addDays($durationDays);

            // Update user status
            $user->is_vip = true;
            $user->save();

            // Record Subscription
            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan' => 'vip',
                'price_paid' => $selectedPlan['price'],
                'started_at' => $startedAt,
                'expired_at' => $expiredAt,
                'is_active' => true,
            ]);

            // Record Transaction
            $trx = Transaction::create([
                'user_id' => $user->id,
                'type' => 'subscription',
                'amount' => $selectedPlan['price'],
                'description' => "Langganan {$selectedPlan['name']} ({$durationDays} hari)",
                'status' => 'completed',
                'payment_method' => $paymentMethod,
                'payment_ref' => 'SUB-' . strtoupper(Str::random(10)),
            ]);

            return [
                'subscription' => $sub,
                'transaction' => $trx,
                'plan' => $selectedPlan,
            ];
        });
    }

    /**
     * Boost an item listing (Localhost simulation).
     */
    public function boostItem(User $user, int $itemId, int $days, string $paymentMethod = 'QRIS / Bank Transfer'): array
    {
        /** @var Item|null $item */
        $item = Item::find($itemId);

        if (!$item) {
            throw new \Exception('Barang tidak ditemukan.');
        }

        if ($item->user_id !== $user->id && !$user->isAdmin()) {
            throw new AuthorizationException('Anda hanya dapat mem-boost barang milik Anda sendiri.');
        }

        $boostPackage = collect($this->getBoostPackages())->firstWhere('days', $days);
        $price = $boostPackage ? $boostPackage['price'] : 15000;

        return DB::transaction(function () use ($item, $user, $days, $price, $paymentMethod) {
            $boostExpires = now()->addDays($days);

            $item->update([
                'is_boosted' => true,
                'boost_expires_at' => $boostExpires,
            ]);

            $trx = Transaction::create([
                'user_id' => $user->id,
                'type' => 'boost',
                'amount' => $price,
                'description' => "Iklan Sorotan (Boost {$days} Hari) untuk barang '{$item->title}'",
                'status' => 'completed',
                'payment_method' => $paymentMethod,
                'payment_ref' => 'BST-' . strtoupper(Str::random(10)),
            ]);

            return [
                'item' => $item->fresh(),
                'transaction' => $trx,
                'expires_at' => $boostExpires->toISOString(),
            ];
        });
    }

    /**
     * Purchase extra posting quota (Pay-per-post, Localhost simulation).
     */
    public function purchaseQuota(User $user, int $slots, string $paymentMethod = 'QRIS / Bank Transfer'): array
    {
        $pkg = collect($this->getQuotaPackages())->firstWhere('slots', $slots);
        $price = $pkg ? $pkg['price'] : 10000;

        return DB::transaction(function () use ($user, $slots, $price, $paymentMethod) {
            $user->increment('bonus_post_quota', $slots);
            $user->refresh();

            $trx = Transaction::create([
                'user_id' => $user->id,
                'type' => 'pay_per_post',
                'amount' => $price,
                'description' => "Pembelian Kuota Tambahan +{$slots} Slot Posting",
                'status' => 'completed',
                'payment_method' => $paymentMethod,
                'payment_ref' => 'QTA-' . strtoupper(Str::random(10)),
            ]);

            return [
                'new_bonus_quota' => $user->bonus_post_quota,
                'remaining_quota' => $user->remainingPostQuota(),
                'transaction' => $trx,
            ];
        });
    }

    /**
     * Get transaction history for user.
     */
    public function getUserTransactions(User $user)
    {
        return Transaction::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();
    }
}
