<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\Subscription;
use Livewire\Attributes\Computed;

#[Layout('components.layouts.admin')]
#[Title('Monetization')]
class MonetizationPanel extends Component
{
    use WithPagination;

    public $activeTab = 'transactions'; // 'transactions' or 'vouchers'
    
    // Transaction filters
    public $txTypeFilter = '';
    public $txStatusFilter = '';

    // Voucher form
    public $showVoucherModal = false;
    public $voucherId = null;
    public $code;
    public $description;
    public $quota_amount;
    public $max_claims;
    public $expired_at;
    public $is_active = true;

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    #[Computed]
    public function totalRevenue()
    {
        return Transaction::where('status', 'completed')->sum('amount');
    }

    #[Computed]
    public function activeVipCount()
    {
        return Subscription::where('is_active', true)
            ->where('expired_at', '>', now())
            ->where('plan', 'vip')
            ->count();
    }

    #[Computed]
    public function activeVouchersCount()
    {
        return Voucher::where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })->count();
    }

    public function createVoucher()
    {
        $this->resetValidation();
        $this->reset(['voucherId', 'code', 'description', 'quota_amount', 'max_claims', 'expired_at']);
        $this->is_active = true;
        $this->showVoucherModal = true;
    }

    public function saveVoucher()
    {
        $this->validate([
            'code' => 'required|string|unique:vouchers,code,' . $this->voucherId,
            'description' => 'nullable|string',
            'quota_amount' => 'required|integer|min:1',
            'max_claims' => 'required|integer|min:1',
            'expired_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        if ($this->voucherId) {
            Voucher::findOrFail($this->voucherId)->update([
                'code' => $this->code,
                'description' => $this->description,
                'quota_amount' => $this->quota_amount,
                'max_claims' => $this->max_claims,
                'expired_at' => $this->expired_at,
                'is_active' => $this->is_active,
            ]);
            $this->dispatch('notify', message: 'Voucher updated successfully.');
        } else {
            Voucher::create([
                'code' => $this->code,
                'description' => $this->description,
                'quota_amount' => $this->quota_amount,
                'max_claims' => $this->max_claims,
                'claimed_count' => 0,
                'expired_at' => $this->expired_at,
                'is_active' => $this->is_active,
            ]);
            $this->dispatch('notify', message: 'Voucher created successfully.');
        }

        $this->showVoucherModal = false;
    }

    public function toggleVoucher($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->is_active = !$voucher->is_active;
        $voucher->save();
        $this->dispatch('notify', message: 'Voucher status updated.');
    }

    public function render()
    {
        $transactions = [];
        $vouchers = [];

        if ($this->activeTab === 'transactions') {
            $query = Transaction::query()->with('user');
            
            if ($this->txTypeFilter) {
                $query->where('type', $this->txTypeFilter);
            }
            if ($this->txStatusFilter) {
                $query->where('status', $this->txStatusFilter);
            }
            
            $transactions = $query->latest()->paginate(15);
        } else {
            $vouchers = Voucher::latest()->paginate(15);
        }

        return view('livewire.admin.monetization-panel', [
            'transactions' => $transactions,
            'vouchers' => $vouchers,
        ]);
    }
}
