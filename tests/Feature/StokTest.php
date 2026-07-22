<?php

use App\Models\Jabatan;
use App\Models\Kategori;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->kategori = Kategori::factory()->create();
    $this->satuan = Satuan::factory()->create();

    $this->produk = Produk::factory()->create(['kategori_id' => $this->kategori->id]);
    $this->varianStok0 = ProdukVarian::factory()->create([
        'produk_id' => $this->produk->id,
        'satuan_id' => $this->satuan->id,
        'nama_varian' => 'Stok 0',
        'harga_jual' => 10000,
        'stok' => 0,
        'minimum_stok' => 5,
    ]);

    $this->varianStok5 = ProdukVarian::factory()->create([
        'produk_id' => $this->produk->id,
        'satuan_id' => $this->satuan->id,
        'nama_varian' => 'Stok 5',
        'harga_jual' => 20000,
        'stok' => 5,
        'minimum_stok' => 2,
    ]);

    $this->varianStokNull = ProdukVarian::factory()->create([
        'produk_id' => $this->produk->id,
        'satuan_id' => $this->satuan->id,
        'nama_varian' => 'Unlimited',
        'harga_jual' => 15000,
        'stok' => null,
        'minimum_stok' => null,
    ]);
});

it('prevents adding to cart when stock is 0', function () {
    Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->call('openDetail', $this->varianStok0->id)
        ->call('addToCartFromDetail')
        ->assertSet('cart', []);
});

it('prevents incrementing quantity in cart beyond available stock', function () {
    Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->call('openDetail', $this->varianStok5->id)
        ->set('detailQty', 5)
        ->call('addToCartFromDetail')
        ->call('incrementQty', 0)
        ->assertSet('cart.0.qty', 5);
});

it('allows unlimited transactions when stock tracking is disabled (stok is null)', function () {
    Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->call('openDetail', $this->varianStokNull->id)
        ->set('detailQty', 100)
        ->call('addToCartFromDetail')
        ->assertSet('cart.0.qty', 100)
        ->call('openPayment')
        ->set('bayar', '1500000')
        ->call('processPayment')
        ->assertSet('showSuccess', true);

    expect($this->varianStokNull->fresh()->stok)->toBeNull();
});

it('decrements stock correctly on successful payment', function () {
    Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->call('openDetail', $this->varianStok5->id)
        ->set('detailQty', 2)
        ->call('addToCartFromDetail')
        ->call('openPayment')
        ->set('bayar', '50000')
        ->call('processPayment')
        ->assertSet('showSuccess', true);

    expect($this->varianStok5->fresh()->stok)->toBe(3);
});

it('displays today summary cards and menu shortcuts on dashboard', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Transaksi Hari Ini')
        ->assertSee('Omzet Hari Ini')
        ->assertSee('Total Produk')
        ->assertSee('Menu Akses Cepat');
});

it('displays low stock notification on dashboard for users with permission and excludes null stock', function () {
    $admin = User::factory()->create();
    $jabatan = Jabatan::create(['nama' => 'Admin Stok']);
    $perm = Permission::firstOrCreate(['nama' => 'stok.notifikasi', 'keterangan' => 'Lihat notifikasi stok minimal']);
    $jabatan->permissions()->sync([$perm->id]);
    $admin->update(['jabatan_id' => $jabatan->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Peringatan Stok Minimal')
        ->assertSee('Stok 0')
        ->assertDontSee('Unlimited');
});

it('hides low stock notification on dashboard for users without permission', function () {
    $staff = User::factory()->create();
    $jabatan = Jabatan::create(['nama' => 'Staff Regular']);
    $staff->update(['jabatan_id' => $jabatan->id]);

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Peringatan Stok Minimal');
});
