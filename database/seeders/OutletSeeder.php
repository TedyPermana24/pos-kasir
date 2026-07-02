<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Outlet::firstOrCreate(
            ['nama' => 'QUEEN CAFE'],
            [
                'alamat' => 'Jl. Merdeka No. 123, Jakarta',
                'telepon' => '081234567890',
            ]
        );
    }
}
