<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;

// Public
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Auth (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// Admin routes
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', \App\Livewire\Admin\Dashboard::class)->name('dashboard');
    Route::get('/users', \App\Livewire\Admin\UserManagement::class)->name('users');
    Route::get('/items', \App\Livewire\Admin\ItemModeration::class)->name('items');
    Route::get('/banners', \App\Livewire\Admin\BannerManagement::class)->name('banners');
    Route::get('/monetization', \App\Livewire\Admin\MonetizationPanel::class)->name('monetization');
    Route::get('/reports', \App\Livewire\Admin\ReportPanel::class)->name('reports');
    Route::get('/settings', \App\Livewire\Admin\SystemSettings::class)->name('settings');
});
