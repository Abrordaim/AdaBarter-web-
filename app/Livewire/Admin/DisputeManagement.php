<?php

namespace App\Livewire\Admin;

use App\Models\Dispute;
use App\Services\EscrowService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Sengketa Transaksi Escrow')]
class DisputeManagement extends Component
{
    use WithPagination;

    public string $statusFilter = 'all'; // 'all', 'open', 'resolved'
    public string $search = '';

    public ?Dispute $selectedDispute = null;
    public string $resolutionType = 'refund_full'; // 'refund_full', 'release', 'refund_partial'
    public ?int $refundAmount = null;
    public string $adminNote = '';
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

    public function viewDispute(int $id): void
    {
        $this->selectedDispute = Dispute::with([
            'offer.payer',
            'offer.receiver',
            'offer.targetItem',
            'offer.offererItems',
            'reporter',
            'resolver',
        ])->findOrFail($id);

        $this->resolutionType = 'refund_full';
        $this->refundAmount = null;
        $this->adminNote = '';
        $this->errorMessage = null;
    }

    public function closeModal(): void
    {
        $this->selectedDispute = null;
        $this->errorMessage = null;
    }

    public function resolve(EscrowService $escrowService): void
    {
        if (!$this->selectedDispute || !$this->selectedDispute->isOpen()) {
            $this->errorMessage = 'Sengketa ini tidak dapat diselesaikan atau sudah selesai.';
            return;
        }

        $offer = $this->selectedDispute->offer;
        $diff = (int) ($offer?->price_difference ?? 0);

        if ($this->resolutionType === 'refund_partial') {
            if (!$this->refundAmount || $this->refundAmount <= 0 || $this->refundAmount >= $diff) {
                $this->errorMessage = 'Untuk pengembalian parsial, masukkan nominal refund antara Rp 1 dan Rp ' . number_format($diff - 1, 0, ',', '.');
                return;
            }
        }

        try {
            $resolved = $escrowService->resolveDispute(
                admin: auth()->user(),
                dispute: $this->selectedDispute,
                resolution: $this->resolutionType,
                refundAmount: $this->resolutionType === 'refund_partial' ? (int) $this->refundAmount : null,
                adminNote: $this->adminNote ?: null
            );

            $this->selectedDispute = $resolved;
            $this->successMessage = 'Sengketa barter #' . $resolved->id . ' berhasil diputuskan dan dieksekusi.';
            $this->dispatch('notify', message: $this->successMessage);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menyelesaikan sengketa: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $query = Dispute::query()->with([
            'offer.payer',
            'offer.receiver',
            'offer.targetItem',
            'reporter',
            'resolver',
        ]);

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('reason', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhere('offer_id', 'like', '%' . $this->search . '%')
                  ->orWhereHas('reporter', function ($sub) {
                      $sub->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                  });
            });
        }

        $disputes = $query->latest()->paginate(10);
        $openCount = Dispute::where('status', 'open')->count();
        $resolvedCount = Dispute::where('status', 'resolved')->count();
        $totalCount = Dispute::count();

        return view('livewire.admin.dispute-management', [
            'disputes' => $disputes,
            'openCount' => $openCount,
            'resolvedCount' => $resolvedCount,
            'totalCount' => $totalCount,
        ]);
    }
}
