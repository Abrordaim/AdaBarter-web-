<?php

namespace Database\Seeders;

use App\Models\SlotPackage;
use Illuminate\Database\Seeder;

class SlotPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name'       => '+1 Slot Barang',
                'slots'      => 1,
                'price'      => 10000,
                'badge'      => null,
                'is_active'  => true,
                'sort_order' => 1,
            ],
            [
                'name'       => '+3 Slot Barang',
                'slots'      => 3,
                'price'      => 25000,
                'badge'      => 'Hemat Rp 5.000',
                'is_active'  => true,
                'sort_order' => 2,
            ],
            [
                'name'       => '+5 Slot Barang',
                'slots'      => 5,
                'price'      => 40000,
                'badge'      => 'Hemat Rp 10.000',
                'is_active'  => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $pkg) {
            SlotPackage::firstOrCreate(['slots' => $pkg['slots']], $pkg);
        }
    }
}
