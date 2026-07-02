<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JabatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all available permissions
        $allPermissions = [
            ['nama' => 'produk.view', 'keterangan' => 'Lihat daftar produk'],
            ['nama' => 'produk.create', 'keterangan' => 'Tambah produk'],
            ['nama' => 'produk.edit', 'keterangan' => 'Edit produk'],
            ['nama' => 'produk.delete', 'keterangan' => 'Hapus produk'],
            ['nama' => 'promo.view', 'keterangan' => 'Lihat daftar promo'],
            ['nama' => 'promo.create', 'keterangan' => 'Tambah promo'],
            ['nama' => 'promo.edit', 'keterangan' => 'Edit promo'],
            ['nama' => 'promo.delete', 'keterangan' => 'Hapus promo'],
            ['nama' => 'pajak.manage', 'keterangan' => 'Kelola pengaturan pajak'],
            ['nama' => 'transaksi.create', 'keterangan' => 'Buat transaksi baru (kasir)'],
            ['nama' => 'transaksi.view', 'keterangan' => 'Lihat riwayat transaksi'],
            ['nama' => 'laporan.view', 'keterangan' => 'Lihat laporan keuangan'],
            ['nama' => 'pegawai.manage', 'keterangan' => 'Kelola data pegawai & jabatan'],
            ['nama' => 'outlet.manage', 'keterangan' => 'Kelola profil outlet'],
        ];

        foreach ($allPermissions as $permData) {
            Permission::firstOrCreate(['nama' => $permData['nama']], $permData);
        }

        // Create jabatan: Admin — all permissions
        $jabatanAdmin = Jabatan::firstOrCreate(['nama' => 'Admin']);
        $jabatanAdmin->permissions()->sync(Permission::all()->pluck('id'));

        // Create jabatan: Kasir — only transaksi permissions
        $jabatanKasir = Jabatan::firstOrCreate(['nama' => 'Kasir']);
        $kasirPermissions = Permission::whereIn('nama', [
            'produk.view',
            'transaksi.create',
            'transaksi.view',
        ])->pluck('id');
        $jabatanKasir->permissions()->sync($kasirPermissions);

        // Create default Admin user (no jabatan = superadmin, has all permissions)
        User::firstOrCreate(
            ['email' => 'admin@pos.app'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@pos.app',
                'jabatan_id' => null,
                'pin' => Hash::make('000000'),
            ]
        );
    }
}
