<?php

namespace Database\Factories;

use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaksi>
 */
class TransaksiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no_referensi' => 'INV-'.fake()->unique()->numerify('##########'),
            'user_id' => User::factory(),
            'nama_pelanggan' => fake()->name(),
            'subtotal' => 100000,
            'total_pajak' => 11000,
            'total_diskon' => 0,
            'diskon_produk' => 0,
            'diskon_keranjang' => 0,
            'grand_total' => 111000,
            'bayar' => 120000,
            'kembalian' => 9000,
        ];
    }
}
