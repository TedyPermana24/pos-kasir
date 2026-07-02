<?php

namespace Database\Factories;

use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'nama' => fake()->randomElement([
                'Promo Akhir Tahun',
                'Diskon Spesial',
                'Promo Kemerdekaan',
                'Flash Sale',
                'Promo Hari Raya',
                'Cuci Gudang',
                'Promo Buka Toko',
                'Promo Weekend',
            ]).' '.fake()->numberBetween(1, 100),
            'tanggal_mulai' => $startDate,
            'tanggal_selesai' => fake()->dateTimeBetween($startDate, '+3 months'),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the promo is active and within date range.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'tanggal_mulai' => now()->subDays(5),
            'tanggal_selesai' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the promo has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'tanggal_mulai' => now()->subMonths(2),
            'tanggal_selesai' => now()->subDays(1),
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the promo is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
