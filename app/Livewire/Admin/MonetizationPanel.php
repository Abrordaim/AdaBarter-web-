<?php

namespace App\Livewire\Admin;

use App\Models\BoostPackage;
use App\Models\SlotPackage;
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

    /** @var string 'transactions'|'vouchers'|'slot_packages'|'vip_plans'|'boost_packages' */
    public string $activeTab = 'transactions';

    // Transaction filters
    public string $txTypeFilter   = '';
    public string $txStatusFilter = '';

    // ─── Voucher form ────────────────────────────────────────────────
    public bool    $showVoucherModal = false;
    public ?int    $voucherId        = null;
    public string  $code             = '';
    public string  $description      = '';
    public ?int    $quota_amount     = null;
    public ?int    $max_claims       = null;
    public ?string $expired_at       = null;
    public bool    $is_active        = true;

    // ─── Slot Package form ───────────────────────────────────────────
    public bool    $showSlotModal  = false;
    public ?int    $editingSlotId  = null;
    public string  $slotName       = '';
    public ?int    $slotSlots      = null;
    public ?float  $slotPrice      = null;
    public string  $slotBadge      = '';
    public bool    $slotIsActive   = true;
    public int     $slotSortOrder  = 0;

    // ─── VIP Plan form ───────────────────────────────────────────────
    public bool    $showVipModal       = false;
    public ?int    $editingVipId       = null;
    public string  $vipName            = '';
    public ?int    $vipDurationDays    = null;
    public ?float  $vipPrice           = null;
    public string  $vipTag             = '';
    public string  $vipBadge           = '';
    public string  $vipFeatures        = '';  // textarea: one feature per line
    public bool    $vipIsActive        = true;
    public int     $vipSortOrder       = 0;

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
        $this->reset(['voucherId', 'code', 'description', 'quota_amount', 'max_claims', 'expired_at']);
        $this->is_active       = true;
        $this->showVoucherModal = true;
    }

    public function saveVoucher(): void
    {
        $this->validate([
            'code'         => 'required|string|unique:vouchers,code,' . $this->voucherId,
            'description'  => 'nullable|string',
            'quota_amount' => 'required|integer|min:1',
            'max_claims'   => 'required|integer|min:1',
            'expired_at'   => 'nullable|date',
            'is_active'    => 'boolean',
        ]);

        $data = [
            'code'         => strtoupper($this->code),
            'description'  => $this->description,
            'quota_amount' => $this->quota_amount,
            'max_claims'   => $this->max_claims,
            'expired_at'   => $this->expired_at,
            'is_active'    => $this->is_active,
        ];

        if ($this->voucherId) {
            Voucher::findOrFail($this->voucherId)->update($data);
            $this->dispatch('notify', message: 'Voucher berhasil diperbarui.');
        } else {
            Voucher::create(array_merge($data, ['claimed_count' => 0]));
            $this->dispatch('notify', message: 'Voucher berhasil dibuat.');
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

    // ─── Slot Package CRUD ───────────────────────────────────────────

    public function createSlot(): void
    {
        $this->resetValidation();
        $this->resetSlotForm();
        $this->editingSlotId = null;
        $this->showSlotModal = true;
    }

    public function editSlot(int $id): void
    {
        $pkg = SlotPackage::findOrFail($id);
        $this->resetValidation();
        $this->editingSlotId = $id;
        $this->slotName      = $pkg->name;
        $this->slotSlots     = $pkg->slots;
        $this->slotPrice     = (float) $pkg->price;
        $this->slotBadge     = $pkg->badge ?? '';
        $this->slotIsActive  = $pkg->is_active;
        $this->slotSortOrder = $pkg->sort_order;
        $this->showSlotModal = true;
    }

    public function saveSlot(): void
    {
        $this->validate([
            'slotName'      => 'required|string|max:100',
            'slotSlots'     => 'required|integer|min:1',
            'slotPrice'     => 'required|numeric|min:0',
            'slotBadge'     => 'nullable|string|max:100',
            'slotSortOrder' => 'required|integer|min:0',
        ]);

        $data = [
            'name'       => $this->slotName,
            'slots'      => $this->slotSlots,
            'price'      => $this->slotPrice,
            'badge'      => $this->slotBadge ?: null,
            'is_active'  => $this->slotIsActive,
            'sort_order' => $this->slotSortOrder,
        ];

        if ($this->editingSlotId) {
            SlotPackage::findOrFail($this->editingSlotId)->update($data);
            $this->dispatch('notify', message: 'Paket slot berhasil diperbarui.');
        } else {
            SlotPackage::create($data);
            $this->dispatch('notify', message: 'Paket slot berhasil dibuat.');
        }

        $this->showSlotModal = false;
    }

    public function toggleSlot(int $id): void
    {
        $pkg = SlotPackage::findOrFail($id);
        $pkg->is_active = ! $pkg->is_active;
        $pkg->save();
        $this->dispatch('notify', message: 'Status paket slot diperbarui.');
    }

    public function deleteSlot(int $id): void
    {
        SlotPackage::findOrFail($id)->delete();
        $this->dispatch('notify', message: 'Paket slot dihapus.');
    }

    private function resetSlotForm(): void
    {
        $this->slotName      = '';
        $this->slotSlots     = null;
        $this->slotPrice     = null;
        $this->slotBadge     = '';
        $this->slotIsActive  = true;
        $this->slotSortOrder = 0;
    }

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
        $this->editingVipId      = $id;
        $this->vipName           = $plan->name;
        $this->vipDurationDays   = $plan->duration_days;
        $this->vipPrice          = (float) $plan->price;
        $this->vipTag            = $plan->tag ?? '';
        $this->vipBadge          = $plan->badge ?? '';
        $this->vipFeatures       = implode("\n", $plan->features ?? []);
        $this->vipIsActive       = $plan->is_active;
        $this->vipSortOrder      = $plan->sort_order;
        $this->showVipModal      = true;
    }

    public function saveVip(): void
    {
        $this->validate([
            'vipName'         => 'required|string|max:100',
            'vipDurationDays' => 'required|integer|min:1',
            'vipPrice'        => 'required|numeric|min:0',
            'vipTag'          => 'nullable|string|max:100',
            'vipBadge'        => 'nullable|string|max:100',
            'vipFeatures'     => 'nullable|string',
            'vipSortOrder'    => 'required|integer|min:0',
        ]);

        // Parse features: one per line, filter empty lines
        $features = collect(explode("\n", $this->vipFeatures))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->toArray();

        $data = [
            'name'          => $this->vipName,
            'duration_days' => $this->vipDurationDays,
            'price'         => $this->vipPrice,
            'tag'           => $this->vipTag ?: null,
            'badge'         => $this->vipBadge ?: null,
            'features'      => $features ?: null,
            'is_active'     => $this->vipIsActive,
            'sort_order'    => $this->vipSortOrder,
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
        $this->vipName          = '';
        $this->vipDurationDays  = null;
        $this->vipPrice         = null;
        $this->vipTag           = '';
        $this->vipBadge         = '';
        $this->vipFeatures      = '';
        $this->vipIsActive      = true;
        $this->vipSortOrder     = 0;
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
        $slotPackages  = collect();
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

            'slot_packages' => $slotPackages = SlotPackage::orderBy('sort_order')->paginate(20),

            'vip_plans' => $vipPlans = VipPlan::orderBy('sort_order')->paginate(20),

            'boost_packages' => $boostPackages = BoostPackage::orderBy('sort_order')->paginate(20),

            default => null,
        };

        return view('livewire.admin.monetization-panel', compact(
            'transactions',
            'vouchers',
            'slotPackages',
            'vipPlans',
            'boostPackages',
        ));
    }
}
