<?php

namespace Database\Seeders;

use App\Models\Pajak;
use Illuminate\Database\Seeder;

class PajakSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pajak::firstOrCreate(
            ['id' => 1],
            [
                'nama' => 'PPN',
                'persentase' => 11.00,
                'is_active' => false,
            ]
        );
    }
}
