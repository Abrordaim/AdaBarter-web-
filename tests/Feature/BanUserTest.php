<?php

use App\Models\Item;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// -------------------------------------------------------------------------
// 1. Login blocked for banned users
// -------------------------------------------------------------------------
test('banned user cannot login', function () {
    $user = User::factory()->create([
        'password'  => bcrypt('password123'),
        'is_banned' => true,
        'banned_at' => now(),
        'ban_reason' => 'Test ban',
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
             ->assertJsonPath('success', false);
});

// -------------------------------------------------------------------------
// 2. Banned user with valid token cannot access protected API
// -------------------------------------------------------------------------
test('banned user with valid token is blocked by middleware and token is revoked', function () {
    $user = User::factory()->create(['is_banned' => false]);
    $token = $user->createToken('mobile')->plainTextToken;

    // Admin bans the user
    $user->update([
        'is_banned'  => true,
        'banned_at'  => now(),
        'ban_reason' => 'Pelanggaran',
    ]);

    $response = $this->withToken($token)->getJson('/api/me');

    $response->assertStatus(403)
             ->assertJsonPath('error_code', 'ACCOUNT_BANNED');

    // All tokens should have been revoked by the middleware
    expect($user->fresh()->tokens()->count())->toBe(0);
});

// -------------------------------------------------------------------------
// 3. Normal (non-banned) user can access protected routes
// -------------------------------------------------------------------------
test('non-banned user can access protected api routes', function () {
    $user  = User::factory()->create(['is_banned' => false]);
    $token = $user->createToken('mobile')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/me');

    $response->assertStatus(200)
             ->assertJsonPath('success', true);
});

// -------------------------------------------------------------------------
// 4. Admin ban action sets is_banned = true and revokes tokens
// -------------------------------------------------------------------------
test('admin banUser livewire action sets is_banned and revokes tokens', function () {
    $admin  = User::factory()->create(['role' => 'admin']);
    $victim = User::factory()->create(['is_banned' => false]);
    $victim->createToken('mobile'); // give victim a token

    $report = \App\Models\Report::create([
        'reporter_id'      => $admin->id,
        'reported_user_id' => $victim->id,
        'reportable_type'  => \App\Models\User::class,
        'reportable_id'    => $victim->id,
        'reason'           => 'penipuan',
        'description'      => 'Test ban via admin action',
        'status'           => 'pending',
    ]);

    $component = \Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\UserReportManagement::class)
        ->call('banUser', $report->id);

    $victim->refresh();

    expect($victim->is_banned)->toBeTrue();
    expect($victim->banned_at)->not->toBeNull();
    expect($victim->tokens()->count())->toBe(0);

    expect($report->fresh()->status)->toBe('resolved');
    expect($report->fresh()->action_taken)->toBe('user_banned');
});

// -------------------------------------------------------------------------
// 5. Admin unban action clears is_banned
// -------------------------------------------------------------------------
test('admin unbanUser livewire action clears is_banned', function () {
    $admin  = User::factory()->create(['role' => 'admin']);
    $victim = User::factory()->create([
        'is_banned'  => true,
        'banned_at'  => now(),
        'ban_reason' => 'Was bad',
    ]);

    \Livewire\Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\UserReportManagement::class)
        ->call('unbanUser', $victim->id);

    $victim->refresh();

    expect($victim->is_banned)->toBeFalse();
    expect($victim->banned_at)->toBeNull();
    expect($victim->ban_reason)->toBeNull();
});

// -------------------------------------------------------------------------
// 6. Unbanned user can login again after unban
// -------------------------------------------------------------------------
test('unbanned user can login again', function () {
    $user = User::factory()->create([
        'password'   => bcrypt('password123'),
        'is_banned'  => false,
        'banned_at'  => null,
        'ban_reason' => null,
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonStructure(['data' => ['token']]);
});
