<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Banner;

#[Layout('components.layouts.admin')]
#[Title('Banner Management')]
class BannerManagement extends Component
{
    use WithPagination;

    public $showModal = false;
    public $bannerId = null;

    public $title;
    public $image_url;
    public $redirect_url;
    public $advertiser_name;
    public $position = 'home_top';
    public $is_active = true;
    public $started_at;
    public $expired_at;

    protected $rules = [
        'title' => 'required|string|max:255',
        'image_url' => 'required|string',
        'redirect_url' => 'nullable|url',
        'advertiser_name' => 'nullable|string|max:255',
        'position' => 'required|in:home_top,home_bottom,detail_page',
        'is_active' => 'boolean',
        'started_at' => 'nullable|date',
        'expired_at' => 'nullable|date|after_or_equal:started_at',
    ];

    public function create()
    {
        $this->resetValidation();
        $this->reset(['bannerId', 'title', 'image_url', 'redirect_url', 'advertiser_name', 'position', 'is_active', 'started_at', 'expired_at']);
        $this->is_active = true;
        $this->position = 'home_top';
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $banner = Banner::findOrFail($id);
        $this->bannerId = $banner->id;
        $this->title = $banner->title;
        $this->image_url = $banner->image_url;
        $this->redirect_url = $banner->redirect_url;
        $this->advertiser_name = $banner->advertiser_name;
        $this->position = $banner->position;
        $this->is_active = $banner->is_active;
        $this->started_at = $banner->started_at ? $banner->started_at->format('Y-m-d\TH:i') : null;
        $this->expired_at = $banner->expired_at ? $banner->expired_at->format('Y-m-d\TH:i') : null;
        $this->showModal = true;
    }

    public function save()
    {
        $validatedData = $this->validate();

        if ($this->bannerId) {
            Banner::findOrFail($this->bannerId)->update($validatedData);
            $this->dispatch('notify', message: 'Banner updated successfully.');
        } else {
            Banner::create($validatedData);
            $this->dispatch('notify', message: 'Banner created successfully.');
        }

        $this->showModal = false;
    }

    public function toggleActive($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->is_active = !$banner->is_active;
        $banner->save();
        $this->dispatch('notify', message: 'Banner status updated.');
    }

    public function delete($id)
    {
        Banner::findOrFail($id)->delete();
        $this->dispatch('notify', message: 'Banner deleted successfully.');
    }

    public function render()
    {
        $banners = Banner::latest()->paginate(10);
        return view('livewire.admin.banner-management', ['banners' => $banners]);
    }
}
