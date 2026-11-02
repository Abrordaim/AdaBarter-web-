<?php

namespace App\Livewire\Admin;

use App\Models\BoostPackage;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\VipPlan;
use App\Models\Voucher;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Monetization')]
class MonetizationPanel extends Component
{
    use WithPagination;

    /** @var string 'transactions'|'vouchers'|'vip_plans'|'boost_packages' */
    public string $activeTab = 'transactions';

    // Transaction filters
    public string $txTypeFilter   = '';
    public string $txStatusFilter = '';

    // ─── Voucher form ────────────────────────────────────────────────
    public bool    $showVoucherModal       = false;
    public ?int    $voucherId              = null;
    public string  $code                   = '';
    public string  $description            = '';
    public string  $distribution           = 'public'; // 'public' | 'private'
    public string  $discount_type          = 'fixed';  // 'fixed' | 'percentage'
    public ?int    $discount_amount        = null;     // nominal Rp
    public ?int    $percentage             = null;     // 1 - 100
    public ?int    $max_discount_amount    = null;     // cap for percentage
    public int     $min_transaction_amount = 0;        // min price difference
    public ?int    $max_claims             = null;
    public ?string $expired_at             = null;
    public bool    $is_active              = true;

    // ─── VIP Plan form ───────────────────────────────────────────────
    public bool    $showVipModal          = false;
    public ?int    $editingVipId          = null;
    public string  $vipName               = '';
    public ?int    $vipDurationDays       = null;
    public ?float  $vipPrice              = null;
    public int     $vipBonusPostQuota     = 0;
    public int     $vipBonusBoostQuota    = 0;
    public string  $vipTag                = '';
    public string  $vipBadge              = '';
    public string  $vipFeatures           = '';  // textarea: one feature per line
    public bool    $vipIsActive           = true;
    public int     $vipSortOrder          = 0;

    // ─── Boost Package form ──────────────────────────────────────────
    public bool    $showBoostModal   = false;
    public ?int    $editingBoostId   = null;
    public string  $boostName        = '';
    public ?int    $boostDays        = null;
    public ?float  $boostPrice       = null;
    public string  $boostTag         = '';
    public string  $boostDescription = '';
    public bool    $boostIsActive    = true;
    public int     $boostSortOrder   = 0;

    // ─────────────────────────────────────────────────────────────────

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    #[Computed]
    public function totalRevenue(): float
    {
        return (float) Transaction::where('status', 'completed')->sum('amount');
    }

    #[Computed]
    public function activeVipCount(): int
    {
        return Subscription::where('is_active', true)
            ->where('expired_at', '>', now())
            ->where('plan', 'vip')
            ->count();
    }

    #[Computed]
    public function activeVouchersCount(): int
    {
        return Voucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })->count();
    }

    // ─── Voucher CRUD ────────────────────────────────────────────────

    public function createVoucher(): void
    {
        $this->resetValidation();
        $this->reset([
            'voucherId', 'code', 'description', 'distribution',
            'discount_type', 'discount_amount', 'percentage',
            'max_discount_amount', 'min_transaction_amount',
            'max_claims', 'expired_at'
        ]);
        $this->distribution           = 'public';
        $this->discount_type          = 'fixed';
        $this->min_transaction_amount = 0;
        $this->is_active              = true;
        $this->showVoucherModal       = true;
    }

    public function editVoucher(int $id): void
    {
        $voucher = Voucher::findOrFail($id);
        $this->resetValidation();
        $this->voucherId              = $voucher->id;
        $this->code                   = $voucher->code;
        $this->description            = $voucher->description ?? '';
        $this->distribution           = $voucher->distribution ?? 'public';
        $this->discount_type          = $voucher->type ?? 'fixed';
        $this->discount_amount        = $voucher->discount_amount;
        $this->percentage             = $voucher->percentage;
        $this->max_discount_amount    = $voucher->max_discount_amount;
        $this->min_transaction_amount = (int) ($voucher->min_transaction_amount ?? 0);
        $this->max_claims             = $voucher->max_claims;
        $this->expired_at             = $voucher->expired_at ? $voucher->expired_at->format('Y-m-d\TH:i') : null;
        $this->is_active              = $voucher->is_active;
        $this->showVoucherModal       = true;
    }

    public function saveVoucher(): void
    {
        $rules = [
            'code'                   => 'required|string|max:50|unique:vouchers,code,' . $this->voucherId,
            'description'            => 'nullable|string|max:255',
            'distribution'           => 'required|in:public,private',
            'discount_type'          => 'required|in:fixed,percentage',
            'min_transaction_amount' => 'nullable|integer|min:0',
            'max_claims'             => 'required|integer|min:1',
            'expired_at'             => 'nullable|date',
            'is_active'              => 'boolean',
        ];

        if ($this->discount_type === 'fixed') {
            $rules['discount_amount'] = 'required|integer|min:1000';
        } else {
            $rules['percentage'] = 'required|integer|min:1|max:100';
            $rules['max_discount_amount'] = 'nullable|integer|min:1000';
        }

        $this->validate($rules);

        $data = [
            'code'                   => strtoupper(trim($this->code)),
            'description'            => $this->description ?: null,
            'distribution'           => $this->distribution,
            'type'                   => $this->discount_type,
            'discount_amount'        => $this->discount_type === 'fixed' ? $this->discount_amount : null,
            'percentage'             => $this->discount_type === 'percentage' ? $this->percentage : null,
            'max_discount_amount'    => $this->discount_type === 'percentage' ? ($this->max_discount_amount ?: null) : null,
            'min_transaction_amount' => $this->min_transaction_amount ?: 0,
            'max_claims'             => $this->max_claims,
            'expired_at'             => $this->expired_at ?: null,
            'is_active'              => $this->is_active,
        ];

        if ($this->voucherId) {
            Voucher::findOrFail($this->voucherId)->update($data);
            $this->dispatch('notify', message: 'Voucher diskon berhasil diperbarui.');
        } else {
            Voucher::create(array_merge($data, ['claimed_count' => 0]));
            $this->dispatch('notify', message: 'Voucher diskon berhasil dibuat.');
        }

        $this->showVoucherModal = false;
    }

    public function toggleVoucher(int $id): void
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->is_active = ! $voucher->is_active;
        $voucher->save();
        $this->dispatch('notify', message: 'Status voucher diperbarui.');
    }

    public function deleteVoucher(int $id): void
    {
        Voucher::findOrFail($id)->delete();
        $this->dispatch('notify', message: 'Voucher berhasil dihapus.');
    }

    // ─── Slot Package CRUD ───────────────────────────────────────────

    // ─── VIP Plan CRUD ───────────────────────────────────────────────

    public function createVip(): void
    {
        $this->resetValidation();
        $this->resetVipForm();
        $this->editingVipId = null;
        $this->showVipModal = true;
    }

    public function editVip(int $id): void
    {
        $plan = VipPlan::findOrFail($id);
        $this->resetValidation();
        $this->editingVipId       = $id;
        $this->vipName            = $plan->name;
        $this->vipDurationDays    = $plan->duration_days;
        $this->vipPrice           = (float) $plan->price;
        $this->vipBonusPostQuota  = (int) $plan->bonus_post_quota;
        $this->vipBonusBoostQuota = (int) $plan->bonus_boost_quota;
        $this->vipTag             = $plan->tag ?? '';
        $this->vipBadge           = $plan->badge ?? '';
        $this->vipFeatures        = implode("\n", $plan->features ?? []);
        $this->vipIsActive        = $plan->is_active;
        $this->vipSortOrder       = $plan->sort_order;
        $this->showVipModal       = true;
    }

    public function saveVip(): void
    {
        $this->validate([
            'vipName'            => 'required|string|max:100',
            'vipDurationDays'    => 'required|integer|min:1',
            'vipPrice'           => 'required|numeric|min:0',
            'vipBonusPostQuota'  => 'required|integer|min:0',
            'vipBonusBoostQuota' => 'required|integer|min:0',
            'vipTag'             => 'nullable|string|max:100',
            'vipBadge'           => 'nullable|string|max:100',
            'vipFeatures'        => 'nullable|string',
            'vipSortOrder'       => 'required|integer|min:0',
        ]);

        // Parse features: one per line, filter empty lines
        $features = collect(explode("\n", $this->vipFeatures))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->toArray();

        $data = [
            'name'              => $this->vipName,
            'duration_days'     => $this->vipDurationDays,
            'price'             => $this->vipPrice,
            'bonus_post_quota'  => $this->vipBonusPostQuota,
            'bonus_boost_quota' => $this->vipBonusBoostQuota,
            'tag'               => $this->vipTag ?: null,
            'badge'             => $this->vipBadge ?: null,
            'features'          => $features ?: null,
            'is_active'         => $this->vipIsActive,
            'sort_order'        => $this->vipSortOrder,
        ];

        if ($this->editingVipId) {
            VipPlan::findOrFail($this->editingVipId)->update($data);
            $this->dispatch('notify', message: 'Paket VIP berhasil diperbarui.');
        } else {
            VipPlan::create($data);
            $this->dispatch('notify', message: 'Paket VIP berhasil dibuat.');
        }

        $this->showVipModal = false;
    }

    public function toggleVip(int $id): void
    {
        $plan = VipPlan::findOrFail($id);
        $plan->is_active = ! $plan->is_active;
        $plan->save();
        $this->dispatch('notify', message: 'Status paket VIP diperbarui.');
    }

    public function deleteVip(int $id): void
    {
        VipPlan::findOrFail($id)->delete();
        $this->dispatch('notify', message: 'Paket VIP dihapus.');
    }

    private function resetVipForm(): void
    {
        $this->vipName            = '';
        $this->vipDurationDays    = null;
        $this->vipPrice           = null;
        $this->vipBonusPostQuota  = 0;
        $this->vipBonusBoostQuota = 0;
        $this->vipTag             = '';
        $this->vipBadge           = '';
        $this->vipFeatures        = '';
        $this->vipIsActive        = true;
        $this->vipSortOrder       = 0;
    }

    // ─── Boost Package CRUD ──────────────────────────────────────────

    public function createBoost(): void
    {
        $this->resetValidation();
        $this->resetBoostForm();
        $this->editingBoostId = null;
        $this->showBoostModal = true;
    }

    public function editBoost(int $id): void
    {
        $pkg = BoostPackage::findOrFail($id);
        $this->resetValidation();
        $this->editingBoostId   = $id;
        $this->boostName        = $pkg->name;
        $this->boostDays        = $pkg->days;
        $this->boostPrice       = (float) $pkg->price;
        $this->boostTag         = $pkg->tag ?? '';
        $this->boostDescription = $pkg->description ?? '';
        $this->boostIsActive    = $pkg->is_active;
        $this->boostSortOrder   = $pkg->sort_order;
        $this->showBoostModal   = true;
    }

    public function saveBoost(): void
    {
        $this->validate([
            'boostName'        => 'required|string|max:100',
            'boostDays'        => 'required|integer|min:1',
            'boostPrice'       => 'required|numeric|min:0',
            'boostTag'         => 'nullable|string|max:100',
            'boostDescription' => 'nullable|string|max:255',
            'boostSortOrder'   => 'required|integer|min:0',
        ]);

        $data = [
            'name'        => $this->boostName,
            'days'        => $this->boostDays,
            'price'       => $this->boostPrice,
            'tag'         => $this->boostTag ?: null,
            'description' => $this->boostDescription ?: null,
            'is_active'   => $this->boostIsActive,
            'sort_order'  => $this->boostSortOrder,
        ];

        if ($this->editingBoostId) {
            BoostPackage::findOrFail($this->editingBoostId)->update($data);
            $this->dispatch('notify', message: 'Paket boost berhasil diperbarui.');
        } else {
            BoostPackage::create($data);
            $this->dispatch('notify', message: 'Paket boost berhasil dibuat.');
        }

        $this->showBoostModal = false;
    }

    public function toggleBoost(int $id): void
    {
        $pkg = BoostPackage::findOrFail($id);
        $pkg->is_active = ! $pkg->is_active;
        $pkg->save();
        $this->dispatch('notify', message: 'Status paket boost diperbarui.');
    }

    public function deleteBoost(int $id): void
    {
        BoostPackage::findOrFail($id)->delete();
        $this->dispatch('notify', message: 'Paket boost dihapus.');
    }

    private function resetBoostForm(): void
    {
        $this->boostName        = '';
        $this->boostDays        = null;
        $this->boostPrice       = null;
        $this->boostTag         = '';
        $this->boostDescription = '';
        $this->boostIsActive    = true;
        $this->boostSortOrder   = 0;
    }

    // ─── Render ─────────────────────────────────────────────────────

    public function render()
    {
        $transactions  = collect();
        $vouchers      = collect();
        $vipPlans      = collect();
        $boostPackages = collect();

        match ($this->activeTab) {
            'transactions' => $transactions = Transaction::query()
                ->with('user')
                ->when($this->txTypeFilter, fn ($q) => $q->where('type', $this->txTypeFilter))
                ->when($this->txStatusFilter, fn ($q) => $q->where('status', $this->txStatusFilter))
                ->latest()
                ->paginate(15),

            'vouchers' => $vouchers = Voucher::latest()->paginate(15),

            'vip_plans' => $vipPlans = VipPlan::orderBy('sort_order')->paginate(20),

            'boost_packages' => $boostPackages = BoostPackage::orderBy('sort_order')->paginate(20),

            default => null,
        };

        return view('livewire.admin.monetization-panel', compact(
            'transactions',
            'vouchers',
            'vipPlans',
            'boostPackages',
        ));
    }
}
