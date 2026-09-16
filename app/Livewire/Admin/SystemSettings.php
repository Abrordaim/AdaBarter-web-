<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.admin')]
#[Title('System Settings')]
class SystemSettings extends Component
{
    // App Identity
    public $appName;
    public $appDescription;
    public $supportEmail;
    
    // Platform Parameters
    public $defaultPostQuota;
    public $vipMonthlyPrice;
    public $boostPricePerDay;

    public function mount()
    {
        // Initialize with default or current config values
        // Note: as per instruction, actual persistence can be added later
        $this->appName = 'AdaBarter';
        $this->appDescription = 'Hyperlocal item barter platform designed for ease and security.';
        $this->supportEmail = 'support@adabarter.test';
        
        // Key Design Decision: Kuota posting gratis 3
        $this->defaultPostQuota = 3; 
        
        $this->vipMonthlyPrice = 50000;
        $this->boostPricePerDay = 15000;
    }

    public function saveIdentity()
    {
        $this->validate([
            'appName' => 'required|string|max:255',
            'appDescription' => 'required|string|max:500',
            'supportEmail' => 'required|email',
        ]);
        
        // In a real scenario, you'd write to an options table or .env file here
        session()->flash('notify_identity', 'App identity settings saved successfully!');
    }

    public function savePlatform()
    {
        $this->validate([
            'defaultPostQuota' => 'required|integer|min:1',
            'vipMonthlyPrice' => 'required|integer|min:0',
            'boostPricePerDay' => 'required|integer|min:0',
        ]);

        session()->flash('notify_platform', 'Platform parameters saved successfully!');
    }

    public function render()
    {
        return view('livewire.admin.system-settings');
    }
}
