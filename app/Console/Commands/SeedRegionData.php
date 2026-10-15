<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SeedRegionData extends Command
{
    protected $signature = 'region:seed {--force : Truncate existing data before seeding}';

    protected $description = 'Seed Indonesian region data (provinces, regencies, districts) from emsifa API';

    private const BASE_URL = 'https://www.emsifa.com/api-wilayah-indonesia/api';

    public function handle(): int
    {
        if (Province::count() > 0 && ! $this->option('force')) {
            $this->info('Region data already seeded. Use --force to re-seed.');
            return self::SUCCESS;
        }

        if ($this->option('force')) {
            $this->warn('Truncating existing region data...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            District::truncate();
            Regency::truncate();
            Province::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // 1. Seed Provinces
        $this->info('Fetching provinces...');
        $provinces = $this->fetchJson('/provinces.json');
        if (! $provinces) return self::FAILURE;

        $provinceRows = array_map(fn($p) => [
            'id'   => (int) $p['id'],
            'code' => str_pad((string) $p['id'], 2, '0', STR_PAD_LEFT),
            'name' => $p['name'],
        ], $provinces);

        DB::table('provinces')->insertOrIgnore($provinceRows);
        $this->info('  → ' . count($provinceRows) . ' provinces seeded.');

        // 2. Seed Regencies
        $this->info('Fetching regencies (kabupaten/kota)...');
        $allRegencies = [];
        foreach ($provinces as $province) {
            $regencies = $this->fetchJson("/regencies/{$province['id']}.json");
            if (! $regencies) continue;
            foreach ($regencies as $r) {
                $allRegencies[] = [
                    'id'          => (int) $r['id'],
                    'code'        => $r['id'],
                    'name'        => $r['name'],
                    'province_id' => (int) $r['province_id'],
                ];
            }
        }

        foreach (array_chunk($allRegencies, 100) as $chunk) {
            DB::table('regencies')->insertOrIgnore($chunk);
        }
        $this->info('  → ' . count($allRegencies) . ' regencies seeded.');

        // 3. Seed Districts
        $this->info('Fetching districts (kecamatan)... This may take a while.');
        $allDistricts = [];
        $bar          = $this->output->createProgressBar(count($allRegencies));
        $bar->start();

        foreach ($allRegencies as $regency) {
            $districts = $this->fetchJson("/districts/{$regency['id']}.json");
            if ($districts) {
                foreach ($districts as $d) {
                    $allDistricts[] = [
                        'id'         => (int) $d['id'],
                        'code'       => $d['id'],
                        'name'       => $d['name'],
                        'regency_id' => (int) $d['regency_id'],
                    ];
                }
            }
            $bar->advance();

            // Batch insert every 500 to avoid memory issues
            if (count($allDistricts) >= 500) {
                DB::table('districts')->insertOrIgnore($allDistricts);
                $allDistricts = [];
            }
        }

        // Insert remaining
        if (! empty($allDistricts)) {
            DB::table('districts')->insertOrIgnore($allDistricts);
        }

        $bar->finish();
        $this->newLine();
        $this->info('  → ' . District::count() . ' districts seeded.');

        $this->newLine();
        $this->info('✅ Region data seeded successfully!');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Provinces', Province::count()],
                ['Regencies', Regency::count()],
                ['Districts', District::count()],
            ]
        );

        return self::SUCCESS;
    }

    private function fetchJson(string $path): ?array
    {
        try {
            $response = Http::timeout(30)->get(self::BASE_URL . $path);
            if ($response->successful()) {
                return $response->json();
            }
            $this->error("Failed to fetch {$path}: HTTP " . $response->status());
            return null;
        } catch (\Exception $e) {
            $this->error("Error fetching {$path}: " . $e->getMessage());
            return null;
        }
    }
}
