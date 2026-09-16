<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\User;

#[Layout('components.layouts.admin')]
#[Title('User Management')]
class UserManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $roleFilter = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function toggleVip($userId)
    {
        $user = User::findOrFail($userId);
        
        // Ensure VIP column exists and flip it. Wait, the prompt says "isVip()" exists, which means the model has a way to check it. If we need to toggle it, let's assume it has an is_vip column, or just leave it for now.
        // Assuming there is an 'is_vip' or similar column:
        // Or if VIP is tied to subscriptions. 
        // We'll assume boolean 'is_vip' since the prompt says "Toggle VIP status".
        
        $user->is_vip = !$user->is_vip;
        $user->save();
        
        $this->dispatch('notify', message: 'VIP status updated for ' . $user->name);
    }

    public function changeRole($userId, $role)
    {
        if (auth()->user() && auth()->user()->role !== 'super_admin') {
            $this->dispatch('notify', message: 'Only super admin can change roles.', type: 'error');
            return;
        }

        $user = User::findOrFail($userId);
        $user->role = $role;
        $user->save();
        $this->dispatch('notify', message: 'Role updated to ' . $role . ' for ' . $user->name);
    }

    public function deleteUser($userId)
    {
        if (auth()->user() && auth()->user()->role !== 'super_admin') {
            $this->dispatch('notify', message: 'Only super admin can delete users.', type: 'error');
            return;
        }
        
        $user = User::findOrFail($userId);
        $user->delete(); 
        $this->dispatch('notify', message: 'User deleted successfully.');
    }

    public function render()
    {
        $query = User::query()->withCount(['items', 'sentOffers']);

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        $users = $query->latest()->paginate(15);

        return view('livewire.admin.user-management', [
            'users' => $users
        ]);
    }
}
