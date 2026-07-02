<?php

use App\Models\Kategori;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

test('authenticated users can visit the create product page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('produk.create'))
        ->assertOk();
});

test('can create a product with default variant', function () {
    $this->actingAs(User::factory()->create());

    $kategori = Kategori::factory()->create();
    $satuan = Satuan::factory()->create();

    Livewire::test('pages::produk.create')
        ->set('nama_produk', 'Produk Test')
        ->set('kategori_id', (string) $kategori->id)
        ->set('satuan_id', (string) $satuan->id)
        ->set('harga_jual', '15000')
        ->call('save')
        ->assertRedirect(route('produk.index'));

    $this->assertDatabaseHas('produks', [
        'nama_produk' => 'Produk Test',
        'kategori_id' => $kategori->id,
    ]);

    $this->assertDatabaseHas('produk_varians', [
        'nama_varian' => 'Default',
        'satuan_id' => $satuan->id,
        'harga_jual' => 15000,
    ]);
});

test('can create a product with default variant stock tracking', function () {
    $this->actingAs(User::factory()->create());

    $kategori = Kategori::factory()->create();
    $satuan = Satuan::factory()->create();

    Livewire::test('pages::produk.create')
        ->set('nama_produk', 'Produk Tracked')
        ->set('kategori_id', (string) $kategori->id)
        ->set('satuan_id', (string) $satuan->id)
        ->set('harga_jual', '25000')
        ->set('aturStokModal', true)
        ->set('sku', 'SKU-TEST-001')
        ->set('harga_modal', '18000')
        ->set('stok', '100')
        ->set('minimum_stok', '10')
        ->call('save')
        ->assertRedirect(route('produk.index'));

    $this->assertDatabaseHas('produk_varians', [
        'nama_varian' => 'Default',
        'sku' => 'SKU-TEST-001',
        'harga_modal' => 18000,
        'stok' => 100,
        'minimum_stok' => 10,
    ]);
});

test('required fields are validated', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::produk.create')
        ->set('nama_produk', '')
        ->set('satuan_id', '')
        ->set('harga_jual', '')
        ->call('save')
        ->assertHasErrors(['nama_produk', 'kategori_id', 'satuan_id', 'harga_jual']);
});

test('can create kategori inline', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::produk.create')
        ->set('namaKategoriBaru', 'Kategori Baru')
        ->call('createKategori')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('kategoris', ['nama' => 'Kategori Baru']);
});

test('can create satuan inline', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::produk.create')
        ->set('namaSatuanBaru', 'Unit Baru')
        ->call('createSatuan')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('satuans', ['nama' => 'Unit Baru']);
});

test('can auto-generate sku for default variant', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::produk.create')
        ->set('nama_produk', 'Indomie Goreng')
        ->call('generateSku');

    expect($component->get('sku'))->toStartWith('IN');
});
