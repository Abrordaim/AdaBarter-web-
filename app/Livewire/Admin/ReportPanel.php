<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;
use App\Models\Item;
use App\Models\Offer;
use App\Models\Chat;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.admin')]
#[Title('Reports')]
class ReportPanel extends Component
{
    public $period = 'month'; // 'week', 'month', 'all'

    public function render()
    {
        $startDate = match($this->period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'all' => now()->subYears(10), // essentially all time
            default => now()->startOfMonth(),
        };

        $stats = [
            'users' => User::where('role', 'user')->where('created_at', '>=', $startDate)->count(),
            'items' => Item::where('created_at', '>=', $startDate)->count(),
            'offers' => Offer::where('created_at', '>=', $startDate)->count(),
            'completed_offers' => Offer::where('status', 'completed')->where('updated_at', '>=', $startDate)->count(),
        ];

        // Most active users (simplified: just joining by count from relationships)
        $topUsers = User::where('role', 'user')
            ->withCount(['items', 'sentOffers'])
            ->orderByDesc('items_count')
            ->take(5)
            ->get();

        // Top cities
        $topCities = User::whereNotNull('city')
            ->select('city', DB::raw('count(*) as total'))
            ->groupBy('city')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // Platform Health
        $health = [
            'pending_offers' => Offer::where('status', 'pending')->count(),
            'items_moderation' => Item::where('status', 'moderated')->count(),
            'active_chats' => Chat::count(), // Just total for now
        ];

        return view('livewire.admin.report-panel', [
            'stats' => $stats,
            'topUsers' => $topUsers,
            'topCities' => $topCities,
            'health' => $health,
        ]);
    }
}
