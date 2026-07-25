<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Satuan;
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
            JabatanSeeder::class,
            PajakSeeder::class,
            PromoSeeder::class,
            OutletSeeder::class,
        ]);

        Kategori::firstOrCreate(['nama' => 'Umum']);
        Satuan::firstOrCreate(['nama' => 'Umum']);
    }
}
