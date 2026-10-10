<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Chat;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            SlotPackageSeeder::class,
            VipPlanSeeder::class,
            BoostPackageSeeder::class,
        ]);

        // 1. Users
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@adabarter.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'city' => 'Jakarta Pusat',
                'free_post_quota' => 3,
                'bonus_post_quota' => 0,
                'is_vip' => true,
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@adabarter.com'],
            [
                'name' => 'Staff Moderasi Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'city' => 'Jakarta Selatan',
                'free_post_quota' => 3,
                'bonus_post_quota' => 0,
            ]
        );

        $budi = User::firstOrCreate(
            ['email' => 'budi@adabarter.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'city' => 'Jakarta Selatan',
                'phone' => '081234567890',
                'free_post_quota' => 3,
                'bonus_post_quota' => 1,
            ]
        );

        $siti = User::firstOrCreate(
            ['email' => 'siti@adabarter.com'],
            [
                'name' => 'Siti Nurhaliza',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'city' => 'Surabaya',
                'phone' => '081987654321',
                'free_post_quota' => 3,
                'bonus_post_quota' => 0,
                'is_vip' => true,
            ]
        );

        $reza = User::firstOrCreate(
            ['email' => 'reza@adabarter.com'],
            [
                'name' => 'Reza Rahadian',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'city' => 'Bandung',
                'phone' => '081345678912',
                'free_post_quota' => 3,
                'bonus_post_quota' => 0,
            ]
        );

        // 2. Promotional Banners
        Banner::firstOrCreate(
            ['title' => 'Festival Barter Indonesia 2024'],
            [
                'image_url' => 'https://images.unsplash.com/photo-1556742049-0a67c5574f73?w=800&auto=format&fit=crop',
                'redirect_url' => 'https://adabarter.com/promo/festival-barter',
                'advertiser_name' => 'AdaBarter Official',
                'position' => 'home_top',
                'is_active' => true,
                'started_at' => now(),
                'expired_at' => now()->addMonths(6),
            ]
        );

        Banner::firstOrCreate(
            ['title' => 'Upgrade VIP Sekarang - Bebas Posting Tanpa Batas!'],
            [
                'image_url' => 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=800&auto=format&fit=crop',
                'redirect_url' => 'https://adabarter.com/monetization/vip',
                'advertiser_name' => 'AdaBarter VIP Club',
                'position' => 'home_bottom',
                'is_active' => true,
                'started_at' => now(),
                'expired_at' => now()->addMonths(12),
            ]
        );

        // 3. Demo Items
        $catElektronik = Category::where('slug', 'elektronik')->first();
        $catHobi = Category::where('slug', 'mainan-hobi')->first();
        $catOlahraga = Category::where('slug', 'olahraga-outdoor')->first();
        $catFurniture = Category::where('slug', 'furniture-interior')->first();

        $item1 = Item::firstOrCreate(
            ['title' => 'Sony PlayStation 4 Pro 1TB'],
            [
                'user_id' => $budi->id,
                'category_id' => $catHobi?->id ?? 1,
                'description' => 'PS4 Pro 1TB kondisi 95% normal mulus, stik original 2 unit, kabel HDMI dan kabel power lengkap.',
                'condition' => 'bekas_baik',
                'desired_items' => 'Nintendo Switch V2 atau Smartphone setara',
                'estimated_price' => 3800000,
                'location' => 'Tebet Barat',
                'city' => 'Jakarta Selatan',
                'status' => 'active',
                'is_boosted' => true,
                'boost_expires_at' => now()->addDays(7),
                'images' => [
                    'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=600&auto=format&fit=crop',
                ],
            ]
        );

        $item2 = Item::firstOrCreate(
            ['title' => 'Sepeda Lipat Dahon Boardwalk D8'],
            [
                'user_id' => $siti->id,
                'category_id' => $catOlahraga?->id ?? 1,
                'description' => 'Sepeda lipat Dahon Boardwalk D8 warna krem classic, groupset Shimano 8 speed, jarang dipakai.',
                'condition' => 'bekas_seperti_baru',
                'desired_items' => 'iPad Air 4 / Kamera Mirrorless Canon M50',
                'estimated_price' => 5200000,
                'location' => 'Gubeng',
                'city' => 'Surabaya',
                'status' => 'active',
                'is_boosted' => true,
                'boost_expires_at' => now()->addDays(14),
                'images' => [
                    'https://images.unsplash.com/photo-1532298229144-0ec0c57515c7?w=600&auto=format&fit=crop',
                ],
            ]
        );

        $item3 = Item::firstOrCreate(
            ['title' => 'Mirrorless Fujifilm X-T20 + Lensa 18-55mm'],
            [
                'user_id' => $reza->id,
                'category_id' => $catElektronik?->id ?? 1,
                'description' => 'Fujifilm X-T20 warna silver hitam, sensor bersih no jamur, include 2 baterai dan strap original.',
                'condition' => 'bekas_baik',
                'desired_items' => 'MacBook Air M1 atau iPhone 12',
                'estimated_price' => 7500000,
                'location' => 'Dago',
                'city' => 'Bandung',
                'status' => 'active',
                'is_boosted' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=600&auto=format&fit=crop',
                ],
            ]
        );

        $item4 = Item::firstOrCreate(
            ['title' => 'Kursi Ergonomis Sihoo M57'],
            [
                'user_id' => $budi->id,
                'category_id' => $catFurniture?->id ?? 1,
                'description' => 'Kursi kerja ergonomis full mesh, sandaran lumbal adjustable, pemakaian WFH baru 4 bulan.',
                'condition' => 'bekas_seperti_baru',
                'desired_items' => 'Monitor 27 inch IPS 144Hz',
                'estimated_price' => 1900000,
                'location' => 'Tebet Barat',
                'city' => 'Jakarta Selatan',
                'status' => 'active',
                'is_boosted' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1580481077195-c999837a7b8e?w=600&auto=format&fit=crop',
                ],
            ]
        );

        // 4. Demo Matched Offer between Budi and Siti
        $offer = Offer::firstOrCreate(
            [
                'offerer_user_id' => $budi->id,
                'target_user_id' => $siti->id,
                'offerer_item_id' => $item1->id,
                'target_item_id' => $item2->id,
            ],
            [
                'cash_supplement' => 1000000,
                'cash_supplement_by' => 'offerer',
                'status' => 'matched',
                'offerer_approved' => true,
                'target_approved' => true,
                'matched_at' => now()->subDay(),
            ]
        );

        // Initial Chat Room Messages
        Chat::firstOrCreate(
            [
                'offer_id' => $offer->id,
                'type' => 'system',
            ],
            [
                'sender_id' => $siti->id,
                'message' => 'Penawaran barter telah disetujui bersama (Matched)! Silakan gunakan ruang obrolan ini untuk mendiskusikan detail kondisi barang dan titik temu pertemuan langsung (COD).',
                'read_at' => now(),
            ]
        );

        Chat::firstOrCreate(
            [
                'offer_id' => $offer->id,
                'sender_id' => $budi->id,
                'message' => 'Halo Mbak Siti, apakah sepedanya masih bisa dicek langsung di Surabaya akhir pekan ini?',
            ],
            [
                'type' => 'text',
                'read_at' => now(),
            ]
        );

        Chat::firstOrCreate(
            [
                'offer_id' => $offer->id,
                'sender_id' => $siti->id,
                'message' => 'Bisa banget Mas Budi, nanti kita ketemuan di area Gubeng atau Grand City Mall ya.',
            ],
            [
                'type' => 'text',
                'read_at' => null,
            ]
        );
    }
}
