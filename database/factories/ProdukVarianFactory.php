<?php

namespace Database\Factories;

use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdukVarian>
 */
class ProdukVarianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hargaModal = fake()->numberBetween(1000, 500000);

        return [
            'produk_id' => Produk::factory(),
            'satuan_id' => Satuan::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'nama_varian' => fake()->randomElement(['Kecil', 'Sedang', 'Besar', 'Regular', 'Jumbo', 'Mini', 'XL', 'XXL']),
            'harga_modal' => $hargaModal,
            'harga_jual' => $hargaModal * fake()->randomFloat(2, 1.1, 2.5),
            'stok' => fake()->numberBetween(0, 500),
            'minimum_stok' => fake()->numberBetween(5, 50),
        ];
    }

    /**
     * Indicate that the variant has no stock/modal tracking.
     */
    public function withoutStockTracking(): static
    {
        return $this->state(fn (array $attributes) => [
            'sku' => null,
            'harga_modal' => null,
            'stok' => null,
            'minimum_stok' => null,
        ]);
    }
}
