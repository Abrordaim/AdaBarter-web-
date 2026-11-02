<?php

namespace Database\Seeders;

use App\Models\VipPlan;
use Illuminate\Database\Seeder;

class VipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'              => 'VIP 1 Bulan',
                'duration_days'     => 30,
                'price'             => 49000,
                'bonus_post_quota'  => 10,
                'bonus_boost_quota' => 2,
                'tag'               => null,
                'badge'             => null,
                'features'          => [
                    '+10 Kuota Slot Posting Tambahan',
                    '+2 Kuota Slot Iklan Sorotan (Boost)',
                    'Lencana VIP emas eksklusif di profil',
                    'Prioritas tampil di rekomendasi barter',
                    'Dukungan bantuan prioritas',
                ],
                'is_active'         => true,
                'sort_order'        => 1,
            ],
            [
                'name'              => 'VIP 3 Bulan',
                'duration_days'     => 90,
                'price'             => 119000,
                'bonus_post_quota'  => 35,
                'bonus_boost_quota' => 8,
                'tag'               => 'Paling Populer',
                'badge'             => 'Hemat 20%',
                'features'          => [
                    '+35 Kuota Slot Posting Tambahan',
                    '+8 Kuota Slot Iklan Sorotan (Boost)',
                    'Semua keuntungan VIP 1 Bulan',
                    'Masa aktif lebih panjang (90 hari)',
                    'Hemat biaya langganan bulanan',
                ],
                'is_active'         => true,
                'sort_order'        => 2,
            ],
            [
                'name'              => 'VIP 1 Tahun',
                'duration_days'     => 365,
                'price'             => 399000,
                'bonus_post_quota'  => 150,
                'bonus_boost_quota' => 30,
                'tag'               => 'Terbaik',
                'badge'             => 'Hemat 35%',
                'features'          => [
                    '+150 Kuota Slot Posting Tambahan',
                    '+30 Kuota Slot Iklan Sorotan (Boost)',
                    'Semua keuntungan paket VIP',
                    'Bebas posting selama 365 hari penuh',
                ],
                'is_active'         => true,
                'sort_order'        => 3,
            ],
        ];

        foreach ($plans as $plan) {
            VipPlan::firstOrCreate(['duration_days' => $plan['duration_days']], $plan);
        }
    }
}
