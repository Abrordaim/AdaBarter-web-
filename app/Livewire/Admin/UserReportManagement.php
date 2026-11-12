<?php

namespace App\Livewire\Admin;

use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use App\Models\UserPenalty;
use App\Services\PenaltyService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Aduan & Laporan Pengguna')]
class UserReportManagement extends Component
{
    use WithPagination;

    public $activeTab = 'reports'; // 'reports' or 'appeals'
    public $statusFilter = 'pending'; // 'all', 'pending', 'resolved'
    public $search = '';
    public $selectedReport = null; // for modal preview if needed

    // Penalty Award Modal State
    public $penaltyModalVisible = false;
    public $targetReportId = null;
    public $penaltyTargetUser = null;
    public $penaltyPoints = 1;
    public $penaltyCategory = 'katalog_menyesatkan';
    public $penaltyReason = '';

    // Appeal review modal state
    public $appealModalVisible = false;
    public $selectedPenalty = null;
    public $appealAdminNotes = '';

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function viewReport($reportId)
    {
        $this->selectedReport = Report::with(['reporter', 'reportedUser', 'reportable', 'resolver'])->find($reportId);
    }

    public function closeReportModal()
    {
        $this->selectedReport = null;
    }

    public function openPenaltyModal($reportId)
    {
        $report = Report::with('reportedUser')->findOrFail($reportId);
        $this->targetReportId = $reportId;
        $this->penaltyTargetUser = $report->reportedUser;
        $this->penaltyPoints = 1;
        $this->penaltyCategory = 'katalog_menyesatkan';
        $this->penaltyReason = 'Pelanggaran terkait laporan #' . $reportId . ' (' . ($report->reason ?? '') . ')';
        $this->penaltyModalVisible = true;
    }

    public function closePenaltyModal()
    {
        $this->penaltyModalVisible = false;
        $this->targetReportId = null;
        $this->penaltyTargetUser = null;
        $this->penaltyPoints = 1;
        $this->penaltyReason = '';
    }

    public function submitAwardPenalty(PenaltyService $penaltyService)
    {
        $report = Report::findOrFail($this->targetReportId);
        if (!$report->reported_user_id) {
            $this->dispatch('notify', message: 'Laporan tidak memiliki target pengguna.');
            return;
        }

        $user = User::findOrFail($report->reported_user_id);

        $penaltyService->awardPoints(
            $user,
            (int) $this->penaltyPoints,
            $this->penaltyCategory,
            $this->penaltyReason,
            $report->id,
            auth()->id()
        );

        $this->closePenaltyModal();
        $this->dispatch('notify', message: "Berhasil memberikan {$this->penaltyPoints} poin penalti kepada {$user->name}.");
    }

    public function openAppealModal($penaltyId)
    {
        $this->selectedPenalty = UserPenalty::with(['user', 'report'])->findOrFail($penaltyId);
        $this->appealAdminNotes = '';
        $this->appealModalVisible = true;
    }

    public function closeAppealModal()
    {
        $this->appealModalVisible = false;
        $this->selectedPenalty = null;
        $this->appealAdminNotes = '';
    }

    public function acceptAppeal(PenaltyService $penaltyService)
    {
        if (!$this->selectedPenalty) return;

        $notes = $this->appealAdminNotes ?: 'Banding disetujui setelah peninjauan bukti.';
        $penaltyService->resolveAppeal($this->selectedPenalty->id, true, $notes, auth()->id());

        $this->closeAppealModal();
        $this->dispatch('notify', message: 'Banding berhasil disetujui. Poin penalti ditarik kembali dan akun dipulihkan.');
    }

    public function rejectAppeal(PenaltyService $penaltyService)
    {
        if (!$this->selectedPenalty) return;

        $notes = $this->appealAdminNotes ?: 'Banding ditolak setelah peninjauan bukti.';
        $penaltyService->resolveAppeal($this->selectedPenalty->id, false, $notes, auth()->id());

        $this->closeAppealModal();
        $this->dispatch('notify', message: 'Banding ditolak. Poin penalti tetap berlaku.');
    }

    public function takedownItem($reportId)
    {
        $report = Report::findOrFail($reportId);
        
        if ($report->reportable_type === Item::class && $report->reportable) {
            $item = $report->reportable;
            $item->status = 'moderated';
            $item->save();
        }

        $report->status = 'resolved';
        $report->action_taken = 'item_moderated';
        $report->resolved_by = auth()->id();
        $report->resolved_at = now();
        $report->save();

        $this->dispatch('notify', message: 'Barang berhasil di-takedown (dinonaktifkan) dan laporan ditandai selesai.');
        if ($this->selectedReport && $this->selectedReport->id === $reportId) {
            $this->selectedReport = $report->fresh(['reporter', 'reportedUser', 'reportable', 'resolver']);
        }
    }

    public function deleteItem($reportId)
    {
        $report = Report::findOrFail($reportId);

        if ($report->reportable_type === Item::class && $report->reportable) {
            $report->reportable->delete();
        }

        $report->status = 'resolved';
        $report->action_taken = 'item_deleted';
        $report->resolved_by = auth()->id();
        $report->resolved_at = now();
        $report->save();

        $this->dispatch('notify', message: 'Barang berhasil dihapus dan laporan ditandai selesai.');
        if ($this->selectedReport && $this->selectedReport->id === $reportId) {
            $this->selectedReport = $report->fresh(['reporter', 'reportedUser', 'reportable', 'resolver']);
        }
    }

    public function banUser($reportId, PenaltyService $penaltyService)
    {
        $report = Report::findOrFail($reportId);

        if ($report->reported_user_id) {
            $user = User::find($report->reported_user_id);
            if ($user) {
                // Award 15 points (Tier 4 Ban)
                $penaltyService->awardPoints(
                    $user,
                    15,
                    'pelanggaran_berat',
                    'Pelanggaran aturan komunitas AdaBarter (laporan #' . $report->id . ')',
                    $report->id,
                    auth()->id()
                );
            }
        }

        $this->dispatch('notify', message: 'Pengguna berhasil di-ban: akun dinonaktifkan, sesi dicabut, dan barang dimoderasi.');
        if ($this->selectedReport && $this->selectedReport->id === $reportId) {
            $this->selectedReport = $report->fresh(['reporter', 'reportedUser', 'reportable', 'resolver']);
        }
    }

    public function unbanUser($userId, PenaltyService $penaltyService)
    {
        $user = User::findOrFail($userId);

        UserPenalty::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'revoked']);

        $user->is_banned = false;
        $user->banned_at = null;
        $user->ban_reason = null;
        $user->save();

        $penaltyService->recalculateUserPoints($user);

        Item::where('user_id', $user->id)->where('status', 'moderated')->update(['status' => 'active']);

        $this->dispatch('notify', message: 'Akun pengguna "' . $user->name . '" berhasil di-unban. Mereka kini dapat login dan beraktivitas kembali.');
    }

    public function dismissReport($reportId)
    {
        $report = Report::findOrFail($reportId);
        $report->status = 'resolved';
        $report->action_taken = 'dismissed';
        $report->resolved_by = auth()->id();
        $report->resolved_at = now();
        $report->save();

        $this->dispatch('notify', message: 'Laporan telah diabaikan/ditolak.');
        if ($this->selectedReport && $this->selectedReport->id === $reportId) {
            $this->selectedReport = $report->fresh(['reporter', 'reportedUser', 'reportable', 'resolver']);
        }
    }

    public function deleteReport($reportId)
    {
        $report = Report::findOrFail($reportId);
        $report->delete();

        $this->dispatch('notify', message: 'Data laporan berhasil dihapus.');
        if ($this->selectedReport && $this->selectedReport->id === $reportId) {
            $this->selectedReport = null;
        }
    }

    public function render()
    {
        $pendingCount = Report::where('status', 'pending')->count();
        $resolvedCount = Report::where('status', 'resolved')->count();
        $pendingAppealsCount = UserPenalty::where('appeal_status', 'pending')->count();

        if ($this->activeTab === 'appeals') {
            $appealsQuery = UserPenalty::with(['user', 'report', 'admin', 'appealResolver'])
                ->where('appeal_status', 'pending');

            if ($this->search) {
                $appealsQuery->where(function ($q) {
                    $q->where('reason', 'like', '%' . $this->search . '%')
                      ->orWhere('appeal_reason', 'like', '%' . $this->search . '%')
                      ->orWhereHas('user', function ($sub) {
                          $sub->where('name', 'like', '%' . $this->search . '%')
                              ->orWhere('email', 'like', '%' . $this->search . '%');
                      });
                });
            }

            $appeals = $appealsQuery->latest('appealed_at')->paginate(10);

            return view('livewire.admin.user-report-management', [
                'reports' => collect([]),
                'appeals' => $appeals,
                'pendingCount' => $pendingCount,
                'resolvedCount' => $resolvedCount,
                'pendingAppealsCount' => $pendingAppealsCount,
            ]);
        }

        $query = Report::query()->with(['reporter', 'reportedUser', 'reportable', 'resolver']);

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('reason', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhereHas('reporter', function ($sub) {
                      $sub->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('reportedUser', function ($sub) {
                      $sub->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                  });
            });
        }

        $reports = $query->latest()->paginate(10);

        return view('livewire.admin.user-report-management', [
            'reports' => $reports,
            'appeals' => collect([]),
            'pendingCount' => $pendingCount,
            'resolvedCount' => $resolvedCount,
            'pendingAppealsCount' => $pendingAppealsCount,
        ]);
    }
}
