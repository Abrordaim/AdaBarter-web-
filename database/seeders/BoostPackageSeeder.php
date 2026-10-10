<?php

namespace Database\Seeders;

use App\Models\BoostPackage;
use Illuminate\Database\Seeder;

class BoostPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name'        => 'Boost 3 Hari',
                'days'        => 3,
                'price'       => 15000,
                'tag'         => null,
                'description' => 'Posisi teratas feed beranda selama 3 hari',
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Boost 7 Hari',
                'days'        => 7,
                'price'       => 29000,
                'tag'         => 'Paling Diminati',
                'description' => 'Maksimal eksposur barter selama 1 minggu penuh',
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Boost 30 Hari',
                'days'        => 30,
                'price'       => 89000,
                'tag'         => null,
                'description' => 'Prioritas sorotan selama 1 bulan',
                'is_active'   => true,
                'sort_order'  => 3,
            ],
        ];

        foreach ($packages as $pkg) {
            BoostPackage::firstOrCreate(['days' => $pkg['days']], $pkg);
        }
    }
}
