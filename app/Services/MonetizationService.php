<?php

namespace App\Services;

use App\Models\BoostPackage;
use App\Models\Item;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VipPlan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonetizationService
{
    /**
     * Get available VIP subscription plans from DB.
     */
    public function getSubscriptionPlans(): array
    {
        return VipPlan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (VipPlan $plan) => [
                'id'                => $plan->id,
                'name'              => $plan->name,
                'duration_days'     => $plan->duration_days,
                'price'             => (float) $plan->price,
                'formatted_price'   => $plan->formatted_price,
                'tag'               => $plan->tag,
                'badge'             => $plan->badge,
                'features'          => $plan->features ?? [],
                'bonus_post_quota'  => (int) $plan->bonus_post_quota,
                'bonus_boost_quota' => (int) $plan->bonus_boost_quota,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get available Item Boost packages from DB.
     */
    public function getBoostPackages(): array
    {
        return BoostPackage::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (BoostPackage $pkg) => [
                'id'              => $pkg->id,
                'days'            => $pkg->days,
                'price'           => (float) $pkg->price,
                'formatted_price' => $pkg->formatted_price,
                'label'           => $pkg->name,
                'tag'             => $pkg->tag,
                'description'     => $pkg->description,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Subscribe user to a VIP plan using plan's DB integer ID.
     */
    public function subscribe(User $user, int $planId, string $paymentMethod = 'QRIS / Bank Transfer'): array
    {
        $selectedPlan = VipPlan::active()->find($planId);

        if (! $selectedPlan) {
            throw ValidationException::withMessages(['plan' => 'Paket langganan tidak valid atau sudah tidak tersedia.']);
        }

        return DB::transaction(function () use ($user, $selectedPlan, $paymentMethod) {
            $durationDays = $selectedPlan->duration_days;
            $startedAt     = now();
            $currentExpiry = ($user->vip_expires_at && $user->vip_expires_at->isFuture()) ? $user->vip_expires_at : now();
            $expiredAt     = $currentExpiry->copy()->addDays($durationDays);

            // Update user status & benefits
            $user->is_vip = true;
            $user->vip_expires_at = $expiredAt;
            if ($selectedPlan->bonus_post_quota > 0) {
                $user->bonus_post_quota = (int) $user->bonus_post_quota + $selectedPlan->bonus_post_quota;
            }
            if ($selectedPlan->bonus_boost_quota > 0) {
                $user->boost_quota = (int) $user->boost_quota + $selectedPlan->bonus_boost_quota;
            }
            $user->save();

            // Record Subscription
            $sub = Subscription::create([
                'user_id'    => $user->id,
                'plan'       => 'vip',
                'price_paid' => $selectedPlan->price,
                'started_at' => $startedAt,
                'expired_at' => $expiredAt,
                'is_active'  => true,
            ]);

            // Record Transaction
            $trx = Transaction::create([
                'user_id'        => $user->id,
                'type'           => 'subscription',
                'amount'         => $selectedPlan->price,
                'description'    => "Langganan {$selectedPlan->name} ({$durationDays} hari)",
                'status'         => 'completed',
                'payment_method' => $paymentMethod,
                'payment_ref'    => 'SUB-' . strtoupper(Str::random(10)),
            ]);

            return [
                'subscription' => $sub,
                'transaction'  => $trx,
                'plan'         => [
                    'id'            => $selectedPlan->id,
                    'name'          => $selectedPlan->name,
                    'duration_days' => $durationDays,
                    'price'         => (float) $selectedPlan->price,
                ],
            ];
        });
    }

    /**
     * Boost an item listing using package ID or days.
     */
    public function boostItem(
        User $user,
        int $itemId,
        ?int $boostPackageId = null,
        ?int $days = null,
        string $paymentMethod = 'QRIS / Bank Transfer'
    ): array {
        /** @var Item|null $item */
        $item = Item::find($itemId);

        if (! $item) {
            throw new \Exception('Barang tidak ditemukan.');
        }

        if ($item->user_id !== $user->id && ! $user->isAdmin()) {
            throw new AuthorizationException('Anda hanya dapat mem-boost barang milik Anda sendiri.');
        }

        /** @var BoostPackage|null $boostPackage */
        $boostPackage = null;
        if ($boostPackageId) {
            $boostPackage = BoostPackage::active()->find($boostPackageId);
        }
        if (! $boostPackage && $days) {
            $boostPackage = BoostPackage::active()->where('days', $days)->first();
        }

        if (! $boostPackage) {
            throw new \Exception('Paket iklan sorotan (boost) tidak valid atau sudah tidak tersedia.');
        }

        $daysCount = $boostPackage->days;
        $price     = $boostPackage->price;

        return DB::transaction(function () use ($item, $user, $boostPackage, $daysCount, $price, $paymentMethod) {
            $boostExpires = now()->addDays($daysCount);

            $item->update([
                'is_boosted'       => true,
                'boost_expires_at' => $boostExpires,
            ]);

            $trx = Transaction::create([
                'user_id'        => $user->id,
                'type'           => 'boost',
                'amount'         => $price,
                'description'    => "Iklan Sorotan ({$boostPackage->name}) untuk barang '{$item->title}'",
                'status'         => 'completed',
                'payment_method' => $paymentMethod,
                'payment_ref'    => 'BST-' . strtoupper(Str::random(10)),
            ]);

            return [
                'item'        => $item->fresh(),
                'transaction' => $trx,
                'expires_at'  => $boostExpires->toISOString(),
            ];
        });
    }

    /**
     * Boost barang listing menggunakan kuota boost VIP gratis milik user (tanpa bayar).
     */
    public function boostWithQuota(User $user, int $itemId): array
    {
        /** @var Item|null $item */
        $item = Item::find($itemId);

        if (! $item) {
            throw new \Exception('Barang tidak ditemukan.');
        }

        if ($item->user_id !== $user->id && ! $user->isAdmin()) {
            throw new AuthorizationException('Anda hanya dapat mem-boost barang milik Anda sendiri.');
        }

        if ((int) $user->boost_quota <= 0) {
            throw ValidationException::withMessages(['boost_quota' => 'Anda tidak memiliki kuota boost listing VIP yang tersedia.']);
        }

        return DB::transaction(function () use ($item, $user) {
            $user->decrement('boost_quota', 1);
            $user->refresh();

            $days = 7;
            $currentExpiry = ($item->boost_expires_at && $item->boost_expires_at->isFuture()) ? $item->boost_expires_at : now();
            $boostExpires = $currentExpiry->copy()->addDays($days);

            $item->update([
                'is_boosted'       => true,
                'boost_expires_at' => $boostExpires,
            ]);

            $trx = Transaction::create([
                'user_id'        => $user->id,
                'type'           => 'boost',
                'amount'         => 0,
                'description'    => "Iklan Sorotan (7 hari) via Kuota Boost VIP untuk barang '{$item->title}'",
                'status'         => 'completed',
                'payment_method' => 'Kuota Boost VIP',
                'payment_ref'    => 'BST-VIP-' . strtoupper(Str::random(8)),
            ]);

            return [
                'item'                  => $item->fresh(),
                'remaining_boost_quota' => $user->boost_quota,
                'transaction'           => $trx,
                'expires_at'            => $boostExpires->toISOString(),
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
