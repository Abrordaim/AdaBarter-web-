<?php

use App\Models\User;

test('user can register and receives sanctum token with 3 free quota', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@adabarter.com',
        'password' => 'secret123',
        'phone' => '08123456789',
        'city' => 'Surabaya',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'free_post_quota', 'remaining_quota', 'can_post'],
                'token',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'budi@adabarter.com',
        'free_post_quota' => 3,
        'bonus_post_quota' => 0,
        'role' => 'user',
        'city' => 'Surabaya',
    ]);
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'andi@adabarter.com',
        'password' => bcrypt('password123'),
        'free_post_quota' => 3,
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'andi@adabarter.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'andi@adabarter.com');

    expect($response->json('data.token'))->not->toBeNull();
});

test('login fails with invalid password', function () {
    User::factory()->create([
        'email' => 'siti@adabarter.com',
        'password' => bcrypt('correctpassword'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'siti@adabarter.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('authenticated user can fetch profile with remaining quota and logout', function () {
    $user = User::factory()->create([
        'name' => 'Rina Wijaya',
        'email' => 'rina@adabarter.com',
        'free_post_quota' => 3,
        'bonus_post_quota' => 0,
        'city' => 'Malang',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $profileResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/user/profile');

    $profileResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.remaining_quota', 3)
        ->assertJsonPath('data.can_post', true);

    $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout');

    $logoutResponse->assertStatus(200)
        ->assertJsonPath('success', true);
});
