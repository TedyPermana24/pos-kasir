<?php

namespace Database\Seeders;

use App\Models\ProdukVarian;
use App\Models\Promo;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $varians = ProdukVarian::all();

        if ($varians->isEmpty()) {
            return;
        }

        // Active promo with some varians
        $promoAktif = Promo::factory()->active()->create([
            'nama' => 'Promo Pembukaan Toko',
        ]);

        $varians->random(min(3, $varians->count()))->each(function (ProdukVarian $varian) use ($promoAktif) {
            $promoAktif->produkVarians()->attach($varian->id, [
                'minimal_harga_jual' => $varian->harga_jual * 0.9,
            ]);
        });

        // Expired promo
        $promoExpired = Promo::factory()->expired()->create([
            'nama' => 'Flash Sale Kemarin',
        ]);

        $varians->random(min(2, $varians->count()))->each(function (ProdukVarian $varian) use ($promoExpired) {
            $promoExpired->produkVarians()->attach($varian->id, [
                'minimal_harga_jual' => $varian->harga_jual * 0.85,
            ]);
        });

        // Inactive promo
        Promo::factory()->inactive()->create([
            'nama' => 'Promo Draft (Belum Aktif)',
        ]);
    }
}
