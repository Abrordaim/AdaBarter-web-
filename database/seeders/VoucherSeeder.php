<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Voucher;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        Voucher::firstOrCreate(
            ['code' => 'WELCOME2024'],
            [
                'description' => 'Voucher selamat datang',
                'quota_amount' => 2,
                'max_claims' => 100,
            ]
        );

        Voucher::firstOrCreate(
            ['code' => 'BARTERFEST'],
            [
                'description' => 'Promo Barter Festival',
                'quota_amount' => 3,
                'max_claims' => 50,
            ]
        );
    }
}