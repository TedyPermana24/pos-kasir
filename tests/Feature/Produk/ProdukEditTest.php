<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

test('authenticated users can visit the edit product page', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()->for(Kategori::factory())->create();
    ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create();

    $this->get(route('produk.edit', $produk))
        ->assertOk();
});

test('can update a product and its variants', function () {
    $this->actingAs(User::factory()->create());

    $kategori = Kategori::factory()->create();
    $newKategori = Kategori::factory()->create();
    $satuan = Satuan::factory()->create();

    $produk = Produk::factory()->for($kategori)->create();
    $varian = ProdukVarian::factory()->for($produk)->for($satuan)->create();

    Livewire::test('pages::produk.edit', ['produk' => $produk])
        ->set('nama_produk', 'Produk Updated')
        ->set('kategori_id', (string) $newKategori->id)
        ->set('varians.0.nama_varian', 'Varian Updated')
        ->set('varians.0.harga_jual', '99000')
        ->call('save')
        ->assertRedirect(route('produk.index'));

    $this->assertDatabaseHas('produks', [
        'id' => $produk->id,
        'nama_produk' => 'Produk Updated',
        'kategori_id' => $newKategori->id,
    ]);

    $this->assertDatabaseHas('produk_varians', [
        'id' => $varian->id,
        'nama_varian' => 'Varian Updated',
        'harga_jual' => 99000,
    ]);
});

test('required fields are validated on edit', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()->for(Kategori::factory())->create();
    ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create();

    Livewire::test('pages::produk.edit', ['produk' => $produk])
        ->set('nama_produk', '')
        ->set('kategori_id', '')
        ->set('varians.0.nama_varian', '')
        ->set('varians.0.satuan_id', '')
        ->set('varians.0.harga_jual', '')
        ->call('save')
        ->assertHasErrors(['nama_produk', 'kategori_id', 'varians.0.nama_varian', 'varians.0.satuan_id', 'varians.0.harga_jual']);
});

test('existing variants are loaded on mount', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()->for(Kategori::factory())->create();
    ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create(['nama_varian' => 'Kecil']);
    ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create(['nama_varian' => 'Besar']);

    $component = Livewire::test('pages::produk.edit', ['produk' => $produk]);

    expect($component->get('varians'))->toHaveCount(2);
    expect($component->get('varians.0.nama_varian'))->toBe('Kecil');
    expect($component->get('varians.1.nama_varian'))->toBe('Besar');
});

test('stock toggle auto-activates when variant has stock data', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()->for(Kategori::factory())->create();
    ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create([
        'sku' => 'SKU-123',
        'stok' => 50,
    ]);

    $component = Livewire::test('pages::produk.edit', ['produk' => $produk]);

    expect($component->get('varians.0.atur_stok'))->toBeTrue();
});
