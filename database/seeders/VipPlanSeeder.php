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
                'name'          => 'VIP 1 Bulan',
                'duration_days' => 30,
                'price'         => 49000,
                'tag'           => null,
                'badge'         => null,
                'features'      => [
                    'Posting barang tanpa batas kuota',
                    'Lencana VIP emas eksklusif di profil',
                    'Prioritas tampil di rekomendasi barter',
                    'Dukungan bantuan prioritas',
                ],
                'is_active'     => true,
                'sort_order'    => 1,
            ],
            [
                'name'          => 'VIP 3 Bulan',
                'duration_days' => 90,
                'price'         => 119000,
                'tag'           => 'Paling Populer',
                'badge'         => 'Hemat 20%',
                'features'      => [
                    'Semua keuntungan VIP 1 Bulan',
                    'Masa aktif lebih panjang (90 hari)',
                    'Hemat biaya langganan bulanan',
                ],
                'is_active'     => true,
                'sort_order'    => 2,
            ],
            [
                'name'          => 'VIP 1 Tahun',
                'duration_days' => 365,
                'price'         => 399000,
                'tag'           => 'Terbaik',
                'badge'         => 'Hemat 35%',
                'features'      => [
                    'Semua keuntungan paket VIP',
                    'Bebas posting selama 365 hari penuh',
                    'Bonus 2x Free Boost Listing mingguan',
                ],
                'is_active'     => true,
                'sort_order'    => 3,
            ],
        ];

        foreach ($plans as $plan) {
            VipPlan::firstOrCreate(['duration_days' => $plan['duration_days']], $plan);
        }
    }
}
