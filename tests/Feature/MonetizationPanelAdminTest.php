<?php

use App\Livewire\Admin\MonetizationPanel;
use App\Models\BoostPackage;
use App\Models\SlotPackage;
use App\Models\User;
use App\Models\VipPlan;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
    ]);
});

test('admin can access monetization panel and switch tabs', function () {
    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->assertStatus(200)
        ->assertSee('Slot Packages')
        ->assertSee('VIP Plans')
        ->assertSee('Boost Packages')
        ->call('setTab', 'slot_packages')
        ->assertSet('activeTab', 'slot_packages')
        ->call('setTab', 'vip_plans')
        ->assertSet('activeTab', 'vip_plans')
        ->call('setTab', 'boost_packages')
        ->assertSet('activeTab', 'boost_packages');
});

test('admin can create slot package', function () {
    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('setTab', 'slot_packages')
        ->call('createSlot')
        ->assertSet('showSlotModal', true)
        ->set('slotName', '+10 Slot Super')
        ->set('slotSlots', 10)
        ->set('slotPrice', 75000)
        ->set('slotBadge', 'Super Hemat')
        ->set('slotSortOrder', 4)
        ->call('saveSlot')
        ->assertSet('showSlotModal', false)
        ->assertDispatched('notify');

    $this->assertDatabaseHas('slot_packages', [
        'name' => '+10 Slot Super',
        'slots' => 10,
        'price' => 75000,
        'badge' => 'Super Hemat',
        'sort_order' => 4,
        'is_active' => true,
    ]);
});

test('admin can edit slot package', function () {
    $pkg = SlotPackage::create([
        'name' => 'Paket Awal',
        'slots' => 2,
        'price' => 20000,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('editSlot', $pkg->id)
        ->assertSet('editingSlotId', $pkg->id)
        ->assertSet('slotName', 'Paket Awal')
        ->set('slotName', 'Paket Diperbarui')
        ->set('slotPrice', 18000)
        ->call('saveSlot')
        ->assertDispatched('notify');

    $this->assertDatabaseHas('slot_packages', [
        'id' => $pkg->id,
        'name' => 'Paket Diperbarui',
        'price' => 18000,
    ]);
});

test('admin can toggle slot package active status', function () {
    $pkg = SlotPackage::create([
        'name' => 'Paket Toggle',
        'slots' => 1,
        'price' => 10000,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('toggleSlot', $pkg->id)
        ->assertDispatched('notify');

    expect($pkg->fresh()->is_active)->toBeFalse();
});

test('admin can delete slot package', function () {
    $pkg = SlotPackage::create([
        'name' => 'Paket Dihapus',
        'slots' => 1,
        'price' => 10000,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('deleteSlot', $pkg->id)
        ->assertDispatched('notify');

    $this->assertDatabaseMissing('slot_packages', [
        'id' => $pkg->id,
    ]);
});

test('admin can create vip plan', function () {
    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('setTab', 'vip_plans')
        ->call('createVip')
        ->assertSet('showVipModal', true)
        ->set('vipName', 'VIP 6 Bulan')
        ->set('vipDurationDays', 180)
        ->set('vipPrice', 220000)
        ->set('vipTag', 'Promo Khusus')
        ->set('vipBadge', 'Hemat 25%')
        ->set('vipFeatures', "Fitur 1\nFitur 2\nFitur 3")
        ->set('vipSortOrder', 3)
        ->call('saveVip')
        ->assertSet('showVipModal', false)
        ->assertDispatched('notify');

    $this->assertDatabaseHas('vip_plans', [
        'name' => 'VIP 6 Bulan',
        'duration_days' => 180,
        'price' => 220000,
        'tag' => 'Promo Khusus',
        'badge' => 'Hemat 25%',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    $savedPlan = VipPlan::where('name', 'VIP 6 Bulan')->first();
    expect($savedPlan->features)->toBe(['Fitur 1', 'Fitur 2', 'Fitur 3']);
});

test('admin can edit vip plan', function () {
    $plan = VipPlan::create([
        'name' => 'VIP Percobaan',
        'duration_days' => 7,
        'price' => 15000,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('editVip', $plan->id)
        ->assertSet('editingVipId', $plan->id)
        ->assertSet('vipName', 'VIP Percobaan')
        ->set('vipPrice', 12000)
        ->set('vipDurationDays', 14)
        ->call('saveVip')
        ->assertDispatched('notify');

    $this->assertDatabaseHas('vip_plans', [
        'id' => $plan->id,
        'price' => 12000,
        'duration_days' => 14,
    ]);
});

test('admin can toggle vip plan active status', function () {
    $plan = VipPlan::create([
        'name' => 'VIP Toggle',
        'duration_days' => 30,
        'price' => 50000,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('toggleVip', $plan->id)
        ->assertDispatched('notify');

    expect($plan->fresh()->is_active)->toBeFalse();
});

test('admin can delete vip plan', function () {
    $plan = VipPlan::create([
        'name' => 'VIP Dihapus',
        'duration_days' => 30,
        'price' => 50000,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('deleteVip', $plan->id)
        ->assertDispatched('notify');

    $this->assertDatabaseMissing('vip_plans', [
        'id' => $plan->id,
    ]);
});

test('admin can create boost package', function () {
    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('setTab', 'boost_packages')
        ->call('createBoost')
        ->assertSet('showBoostModal', true)
        ->set('boostName', 'Boost 14 Hari')
        ->set('boostDays', 14)
        ->set('boostPrice', 50000)
        ->set('boostTag', 'Paling Laris')
        ->set('boostDescription', 'Sorotan 2 minggu')
        ->set('boostSortOrder', 4)
        ->call('saveBoost')
        ->assertSet('showBoostModal', false)
        ->assertDispatched('notify');

    $this->assertDatabaseHas('boost_packages', [
        'name' => 'Boost 14 Hari',
        'days' => 14,
        'price' => 50000,
        'tag' => 'Paling Laris',
        'description' => 'Sorotan 2 minggu',
        'sort_order' => 4,
        'is_active' => true,
    ]);
});

test('admin can edit boost package', function () {
    $pkg = BoostPackage::create([
        'name' => 'Boost Awal',
        'days' => 5,
        'price' => 20000,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('editBoost', $pkg->id)
        ->assertSet('editingBoostId', $pkg->id)
        ->assertSet('boostName', 'Boost Awal')
        ->set('boostName', 'Boost Diperbarui')
        ->set('boostPrice', 22000)
        ->set('boostDays', 6)
        ->call('saveBoost')
        ->assertDispatched('notify');

    $this->assertDatabaseHas('boost_packages', [
        'id' => $pkg->id,
        'name' => 'Boost Diperbarui',
        'days' => 6,
        'price' => 22000,
    ]);
});

test('admin can toggle boost package active status', function () {
    $pkg = BoostPackage::create([
        'name' => 'Boost Toggle',
        'days' => 3,
        'price' => 15000,
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('toggleBoost', $pkg->id)
        ->assertDispatched('notify');

    expect($pkg->fresh()->is_active)->toBeFalse();
});

test('admin can delete boost package', function () {
    $pkg = BoostPackage::create([
        'name' => 'Boost Dihapus',
        'days' => 3,
        'price' => 15000,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MonetizationPanel::class)
        ->call('deleteBoost', $pkg->id)
        ->assertDispatched('notify');

    $this->assertDatabaseMissing('boost_packages', [
        'id' => $pkg->id,
    ]);
});
