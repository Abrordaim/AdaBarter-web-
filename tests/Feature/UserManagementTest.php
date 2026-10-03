<?php

use App\Livewire\Admin\UserManagement;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->superAdmin = User::factory()->create([
        'name' => 'Super Admin Test',
        'email' => 'superadmin_test@adabarter.com',
        'role' => 'super_admin',
    ]);
});

test('super admin can access user management page', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.users'))
        ->assertStatus(200)
        ->assertSee('Manajemen Pengguna');
});

test('super admin can create a new user', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(UserManagement::class)
        ->call('create')
        ->set('name', 'Pengguna Baru')
        ->set('email', 'pengguna_baru@adabarter.com')
        ->set('password', 'password123')
        ->set('role', 'user')
        ->set('phone', '0812999888')
        ->set('city', 'Bandung')
        ->set('is_vip', true)
        ->set('free_post_quota', 5)
        ->set('bonus_post_quota', 2)
        ->call('save')
        ->assertDispatched('notify');

    $this->assertDatabaseHas('users', [
        'name' => 'Pengguna Baru',
        'email' => 'pengguna_baru@adabarter.com',
        'role' => 'user',
        'phone' => '0812999888',
        'city' => 'Bandung',
        'is_vip' => 1,
        'free_post_quota' => 5,
        'bonus_post_quota' => 2,
    ]);

    $created = User::where('email', 'pengguna_baru@adabarter.com')->first();
    expect(Hash::check('password123', $created->password))->toBeTrue();
});

test('super admin can edit user without changing password', function () {
    $user = User::factory()->create([
        'name' => 'User Lama',
        'email' => 'user_lama@adabarter.com',
        'password' => Hash::make('original_pass'),
        'role' => 'user',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(UserManagement::class)
        ->call('edit', $user->id)
        ->set('name', 'User Terupdate')
        ->set('phone', '0811223344')
        ->call('save')
        ->assertDispatched('notify');

    $user->refresh();
    expect($user->name)->toBe('User Terupdate');
    expect($user->phone)->toBe('0811223344');
    expect(Hash::check('original_pass', $user->password))->toBeTrue();
});

test('super admin can edit user and change password', function () {
    $user = User::factory()->create([
        'name' => 'User Ganti Pass',
        'email' => 'ganti_pass@adabarter.com',
        'password' => Hash::make('old_secret'),
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(UserManagement::class)
        ->call('edit', $user->id)
        ->set('password', 'new_secret_123')
        ->call('save')
        ->assertDispatched('notify');

    $user->refresh();
    expect(Hash::check('new_secret_123', $user->password))->toBeTrue();
});

test('cannot create user with duplicate email', function () {
    User::factory()->create(['email' => 'existing@adabarter.com']);

    Livewire::actingAs($this->superAdmin)
        ->test(UserManagement::class)
        ->call('create')
        ->set('name', 'Duplicate User')
        ->set('email', 'existing@adabarter.com')
        ->set('password', 'password123')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

test('super admin cannot delete own account', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(UserManagement::class)
        ->call('deleteUser', $this->superAdmin->id)
        ->assertDispatched('notify');

    $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
});

test('super admin can delete other user', function () {
    $other = User::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(UserManagement::class)
        ->call('deleteUser', $other->id)
        ->assertDispatched('notify');

    $this->assertDatabaseMissing('users', ['id' => $other->id]);
});
