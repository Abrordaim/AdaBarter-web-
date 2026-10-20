<?php

namespace App\Livewire\Admin;

use App\Models\Withdrawal;
use App\Services\WalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Penarikan Dana Pengguna')]
class WithdrawalManagement extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';
    public string $search = '';

    public ?Withdrawal $selectedWithdrawal = null;
    public string $rejectionReason = '';
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function viewWithdrawal(int $id): void
    {
        $this->selectedWithdrawal = Withdrawal::with(['user', 'processor'])->findOrFail($id);
        $this->rejectionReason = '';
        $this->errorMessage = null;
    }

    public function closeModal(): void
    {
        $this->selectedWithdrawal = null;
        $this->errorMessage = null;
    }

    public function approve(WalletService $walletService): void
    {
        if (!$this->selectedWithdrawal?->isPending()) {
            $this->errorMessage = 'Penarikan ini tidak lagi dalam status pending.';
            return;
        }

        try {
            $approved = $walletService->approveWithdrawal(auth()->user(), $this->selectedWithdrawal);
            $this->selectedWithdrawal = $approved;
            $this->successMessage = "Penarikan #$approved->id berhasil disetujui dan ditandai PAID.";
            $this->dispatch('notify', message: $this->successMessage);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menyetujui: ' . $e->getMessage();
        }
    }

    public function reject(WalletService $walletService): void
    {
        if (!$this->selectedWithdrawal?->isPending()) {
            $this->errorMessage = 'Penarikan ini tidak lagi dalam status pending.';
            return;
        }

        if (empty(trim($this->rejectionReason))) {
            $this->errorMessage = 'Alasan penolakan wajib diisi.';
            return;
        }

        try {
            $rejected = $walletService->rejectWithdrawal(
                auth()->user(),
                $this->selectedWithdrawal,
                trim($this->rejectionReason)
            );
            $this->selectedWithdrawal = $rejected;
            $this->successMessage = "Penarikan #$rejected->id ditolak. Saldo telah dikembalikan ke dompet pengguna.";
            $this->dispatch('notify', message: $this->successMessage);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menolak: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $query = Withdrawal::query()->with(['user', 'processor']);

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('bank_name', 'like', '%' . $this->search . '%')
                  ->orWhere('account_number', 'like', '%' . $this->search . '%')
                  ->orWhere('account_name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function ($sub) {
                      $sub->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                  });
            });
        }

        $withdrawals = $query->latest()->paginate(15);
        $pendingCount = Withdrawal::where('status', 'pending')->count();
        $totalAmountPending = Withdrawal::where('status', 'pending')->sum('amount');

        return view('livewire.admin.withdrawal-management', [
            'withdrawals'       => $withdrawals,
            'pendingCount'      => $pendingCount,
            'totalAmountPending' => $totalAmountPending,
        ]);
    }
}
