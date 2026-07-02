<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
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

        $kategoris = collect([
            'Makanan', 'Minuman', 'Snack', 'Elektronik', 'Alat Tulis',
            'Kebutuhan Rumah', 'Obat-obatan', 'Kosmetik',
        ])->map(fn (string $nama) => Kategori::create(['nama' => $nama]));

        $satuans = collect([
            'Pcs', 'Box', 'Kg', 'Liter', 'Pack',
            'Lusin', 'Rim', 'Karton', 'Botol', 'Sachet',
        ])->map(fn (string $nama) => Satuan::create(['nama' => $nama]));

        Produk::factory(20)
            ->recycle($kategoris)
            ->create()
            ->each(function (Produk $produk) use ($satuans) {
                $varianCount = rand(1, 3);

                ProdukVarian::factory($varianCount)
                    ->for($produk)
                    ->recycle($satuans)
                    ->create();
            });
    }
}
