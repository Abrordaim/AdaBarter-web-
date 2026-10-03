<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('User Management')]
class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';

    // Modal state
    public bool $showModal = false;
    public ?int $userId = null;

    // Form fields
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'user';
    public string $phone = '';
    public string $city = '';
    public bool $is_vip = false;
    public int $free_post_quota = 3;
    public int $bonus_post_quota = 0;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset([
            'userId', 'name', 'email', 'password', 'phone', 'city',
        ]);
        $this->role = 'user';
        $this->is_vip = false;
        $this->free_post_quota = 3;
        $this->bonus_post_quota = 0;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $user = User::findOrFail($id);

        $this->userId           = $user->id;
        $this->name             = $user->name;
        $this->email            = $user->email;
        $this->password         = ''; // Leave blank unless changing
        $this->role             = $user->role;
        $this->phone            = $user->phone ?? '';
        $this->city             = $user->city ?? '';
        $this->is_vip           = (bool) $user->is_vip;
        $this->free_post_quota  = (int) $user->free_post_quota;
        $this->bonus_post_quota = (int) $user->bonus_post_quota;

        $this->showModal = true;
    }

    public function save(): void
    {
        $emailRule = $this->userId
            ? "required|email|max:255|unique:users,email,{$this->userId}"
            : 'required|email|max:255|unique:users,email';

        $passwordRule = $this->userId
            ? 'nullable|string|min:6'
            : 'required|string|min:6';

        $validated = $this->validate([
            'name'             => 'required|string|max:255',
            'email'            => $emailRule,
            'password'         => $passwordRule,
            'role'             => 'required|in:user,admin,super_admin',
            'phone'            => 'nullable|integer|',
            'city'             => 'nullable|string|max:100',
            'is_vip'           => 'boolean',
            'free_post_quota'  => 'required|integer|min:0',
            'bonus_post_quota' => 'required|integer|min:0',
        ]);

        $userData = [
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'role'             => $validated['role'],
            'phone'            => $validated['phone'] ?: null,
            'city'             => $validated['city'] ?: null,
            'is_vip'           => $validated['is_vip'],
            'free_post_quota'  => $validated['free_post_quota'],
            'bonus_post_quota' => $validated['bonus_post_quota'],
        ];

        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        if ($this->userId) {
            $user = User::findOrFail($this->userId);

            // Guard against self-demotion from super_admin
            if ($user->id === auth()->id() && $user->role === 'super_admin' && $validated['role'] !== 'super_admin') {
                $this->dispatch('notify', message: 'Anda tidak dapat mengubah role akun Anda sendiri.', type: 'error');
                return;
            }

            $user->update($userData);
            $this->dispatch('notify', message: 'Data user berhasil diperbarui.');
        } else {
            User::create($userData);
            $this->dispatch('notify', message: 'User baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetPage();
    }

    public function toggleVip(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->is_vip = ! $user->is_vip;
        $user->save();

        $this->dispatch('notify', message: 'Status VIP diperbarui untuk ' . $user->name);
    }

    public function changeRole(int $userId, string $role): void
    {
        if (auth()->user() && auth()->user()->role !== 'super_admin') {
            $this->dispatch('notify', message: 'Hanya super admin yang dapat mengubah role.', type: 'error');
            return;
        }

        $user = User::findOrFail($userId);
        if ($user->id === auth()->id()) {
            $this->dispatch('notify', message: 'Anda tidak dapat mengubah role akun Anda sendiri.', type: 'error');
            return;
        }

        $user->role = $role;
        $user->save();
        $this->dispatch('notify', message: 'Role diperbarui ke ' . $role . ' untuk ' . $user->name);
    }

    public function deleteUser(int $userId): void
    {
        if (auth()->user() && auth()->user()->role !== 'super_admin') {
            $this->dispatch('notify', message: 'Hanya super admin yang dapat menghapus user.', type: 'error');
            return;
        }

        if ($userId === auth()->id()) {
            $this->dispatch('notify', message: 'Anda tidak dapat menghapus akun Anda sendiri.', type: 'error');
            return;
        }

        $user = User::findOrFail($userId);
        $user->delete();
        $this->dispatch('notify', message: 'User berhasil dihapus.');
    }

    public function render()
    {
        $query = User::query()->withCount(['items', 'sentOffers']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('phone', 'like', '%' . $this->search . '%')
                    ->orWhere('city', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        $users = $query->latest()->paginate(15);

        return view('livewire.admin.user-management', [
            'users' => $users,
        ]);
    }
}
