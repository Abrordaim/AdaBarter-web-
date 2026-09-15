<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            VoucherSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@adabarter.com',
            'role' => 'super_admin',
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@adabarter.com',
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@adabarter.com',
            'role' => 'user',
        ]);
    }
}
