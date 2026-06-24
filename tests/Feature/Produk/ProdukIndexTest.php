<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('produk.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the product index', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('produk.index'))
        ->assertOk();
});

test('product list displays products with variants', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()
        ->for(Kategori::factory())
        ->create(['nama_produk' => 'Indomie Goreng']);

    ProdukVarian::factory()
        ->for($produk)
        ->for(Satuan::factory())
        ->create(['nama_varian' => 'Regular', 'harga_jual' => 3500]);

    $this->get(route('produk.index'))
        ->assertOk()
        ->assertSee('Indomie Goreng')
        ->assertSee('1 varian');
});

test('search filters products by name', function () {
    $this->actingAs(User::factory()->create());

    $produk1 = Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Indomie Goreng']);
    $produk2 = Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Teh Botol Sosro']);

    ProdukVarian::factory()->for($produk1)->for(Satuan::factory())->create();
    ProdukVarian::factory()->for($produk2)->for(Satuan::factory())->create();

    $this->get(route('produk.index', ['q' => 'Indomie']))
        ->assertOk()
        ->assertSee('Indomie Goreng')
        ->assertDontSee('Teh Botol Sosro');
});

test('search filters products by variant sku', function () {
    $this->actingAs(User::factory()->create());

    $produk1 = Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Produk A']);
    $produk2 = Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Produk B']);

    ProdukVarian::factory()->for($produk1)->for(Satuan::factory())->create(['sku' => 'SKU-001']);
    ProdukVarian::factory()->for($produk2)->for(Satuan::factory())->create(['sku' => 'SKU-002']);

    $this->get(route('produk.index', ['q' => 'SKU-001']))
        ->assertOk()
        ->assertSee('Produk A')
        ->assertDontSee('Produk B');
});

test('can add a new variant from index modal', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Kopi Kapal Api']);
    ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create(['nama_varian' => 'Kecil', 'harga_jual' => 1500]);

    $satuanBaru = Satuan::factory()->create(['nama' => 'Satuan Unik Test']);

    Livewire::test('pages::produk.index')
        ->call('openAddVarianModal', $produk->id)
        ->set('nama_varian', 'Besar')
        ->set('satuan_id', (string) $satuanBaru->id)
        ->set('harga_jual', '15000')
        ->set('aturStokModal', true)
        ->set('stok', '10')
        ->call('saveNewVarian')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('produk_varians', [
        'produk_id' => $produk->id,
        'nama_varian' => 'Besar',
        'satuan_id' => $satuanBaru->id,
        'harga_jual' => 15000,
        'stok' => 10,
    ]);

    expect($produk->refresh()->varians)->toHaveCount(2);
});

test('filters products by category', function () {
    $this->actingAs(User::factory()->create());

    $kategori1 = Kategori::factory()->create(['nama' => 'Minuman']);
    $kategori2 = Kategori::factory()->create(['nama' => 'Makanan']);

    $produk1 = Produk::factory()->for($kategori1)->create(['nama_produk' => 'Teh Botol']);
    ProdukVarian::factory()->for($produk1)->for(Satuan::factory())->create();

    $produk2 = Produk::factory()->for($kategori2)->create(['nama_produk' => 'Indomie Goreng']);
    ProdukVarian::factory()->for($produk2)->for(Satuan::factory())->create();

    $this->get(route('produk.index', ['kategori' => $kategori1->id]))
        ->assertOk()
        ->assertSee('Teh Botol')
        ->assertDontSee('Indomie Goreng');
});
