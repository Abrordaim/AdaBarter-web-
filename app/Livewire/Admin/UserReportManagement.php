<?php

namespace App\Livewire\Admin;

use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Aduan & Laporan Pengguna')]
class UserReportManagement extends Component
{
    use WithPagination;

    public $statusFilter = 'pending'; // 'all', 'pending', 'resolved'
    public $search = '';
    public $selectedReport = null; // for modal preview if needed

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingSearch()
    {
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

    public function banUser($reportId)
    {
        $report = Report::findOrFail($reportId);

        if ($report->reported_user_id) {
            $user = User::find($report->reported_user_id);
            if ($user) {
                // Revoke all tokens
                $user->tokens()->delete();
                // Moderate all active items of this user
                Item::where('user_id', $user->id)->where('status', 'active')->update(['status' => 'moderated']);
            }
        }

        $report->status = 'resolved';
        $report->action_taken = 'user_banned';
        $report->resolved_by = auth()->id();
        $report->resolved_at = now();
        $report->save();

        $this->dispatch('notify', message: 'Pengguna terlapor berhasil ditindak (sesi login dicabut & barang dinonaktifkan).');
        if ($this->selectedReport && $this->selectedReport->id === $reportId) {
            $this->selectedReport = $report->fresh(['reporter', 'reportedUser', 'reportable', 'resolver']);
        }
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
        $pendingCount = Report::where('status', 'pending')->count();
        $resolvedCount = Report::where('status', 'resolved')->count();

        return view('livewire.admin.user-report-management', [
            'reports' => $reports,
            'pendingCount' => $pendingCount,
            'resolvedCount' => $resolvedCount,
        ]);
    }
}
