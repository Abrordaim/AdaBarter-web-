<?php

use App\Livewire\Admin\UserReportManagement;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->userA = User::factory()->create([
        'name' => 'Pelapor User',
        'email' => 'pelapor@adabarter.com',
    ]);
    $this->tokenA = $this->userA->createToken('test')->plainTextToken;

    $this->userB = User::factory()->create([
        'name' => 'Terlapor User',
        'email' => 'terlapor@adabarter.com',
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin Moderator',
        'email' => 'admin_mod@adabarter.com',
        'role' => 'admin',
    ]);
});

test('user can submit a report against another user account', function () {
    Storage::fake('public');

    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/reports', [
            'reportable_type' => 'user',
            'reportable_id' => $this->userB->id,
            'reason' => 'penipuan',
            'description' => 'Pengguna ini menolak mengirim barang setelah disepakati.',
            'evidence' => UploadedFile::fake()->image('bukti_chat.jpg'),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Laporan Anda telah berhasil dikirim ke Admin. Terima kasih telah menjaga keamanan komunitas AdaBarter.');

    $this->assertDatabaseHas('reports', [
        'reporter_id' => $this->userA->id,
        'reported_user_id' => $this->userB->id,
        'reportable_type' => User::class,
        'reportable_id' => $this->userB->id,
        'reason' => 'penipuan',
        'status' => 'pending',
    ]);
});

test('user cannot report their own account', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson('/api/reports', [
            'reportable_type' => 'user',
            'reportable_id' => $this->userA->id,
            'reason' => 'penipuan',
            'description' => 'Mencoba melaporkan diri sendiri.',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Anda tidak dapat melaporkan akun sendiri.');
});

test('admin can review and take action on user report in livewire', function () {
    $report = Report::create([
        'reporter_id' => $this->userA->id,
        'reported_user_id' => $this->userB->id,
        'reportable_type' => User::class,
        'reportable_id' => $this->userB->id,
        'reason' => 'penipuan',
        'description' => 'Akun penipu terindikasi bot.',
        'status' => 'pending',
    ]);

    Livewire::actingAs($this->admin)
        ->test(UserReportManagement::class)
        ->assertSee('Terlapor User')
        ->assertSee('Penipuan')
        ->call('banUser', $report->id)
        ->assertDispatched('notify');

    $report->refresh();
    expect($report->status)->toBe('resolved');
    expect($report->action_taken)->toBe('user_banned');
    expect($report->resolved_by)->toBe($this->admin->id);
});
