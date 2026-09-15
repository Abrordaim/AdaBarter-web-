<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Elektronik', 'icon' => '📱'],
            ['name' => 'Fashion & Pakaian', 'icon' => '👕'],
            ['name' => 'Furniture & Interior', 'icon' => '🪑'],
            ['name' => 'Olahraga & Outdoor', 'icon' => '⚽'],
            ['name' => 'Buku & Edukasi', 'icon' => '📚'],
            ['name' => 'Mainan & Hobi', 'icon' => '🎮'],
            ['name' => 'Peralatan Rumah', 'icon' => '🏠'],
            ['name' => 'Otomotif', 'icon' => '🚗'],
            ['name' => 'Kesehatan & Kecantikan', 'icon' => '💊'],
            ['name' => 'Lainnya', 'icon' => '📦'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'is_active' => true,
                ]
            );
        }
    }
}