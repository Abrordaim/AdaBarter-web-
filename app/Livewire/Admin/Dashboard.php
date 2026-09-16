<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\Item;
use App\Models\Offer;
use App\Models\Transaction;
use App\Services\UserService;

#[Layout('components.layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    
    #[Computed] 
    public function totalUsers()
    {
        return app(UserService::class)->countUser('user');
        // return User::where('role', 'user')->count();
        // return $this->data->countUser('user');
    }
    

    #[Computed]
    public function totalItemsActive()
    {
        return Item::where('status', 'active')->count();
    }

    #[Computed]
    public function totalOffers()
    {
        return Offer::count();
    }

    #[Computed]
    public function totalCompletedBarters()
    {
        return Offer::where('status', 'completed')->count();
    }

    #[Computed]
    public function revenueThisMonth()
    {
        return Transaction::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
    }

    #[Computed]
    public function recentUsers()
    {
        return User::latest()->take(5)->get();
    }

    #[Computed]
    public function recentItems()
    {
        return Item::where('status', 'moderated')->latest()->take(5)->get();
    }

    #[Computed]
    public function recentBarters()
    {
        return Offer::where('status', 'completed')->latest()->take(5)->get();
    }

    public function render(UserService $userService)
    {
        // $this->data = $userService->countUser('user');
        return view('livewire.admin.dashboard');
    }
}
