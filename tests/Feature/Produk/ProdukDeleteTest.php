<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

test('can soft delete a product and its variants', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()->for(Kategori::factory())->create();
    $varian = ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create();

    Livewire::test('pages::produk.index')
        ->call('confirmDelete', $produk->id)
        ->call('deleteProduk')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('produks', ['id' => $produk->id]);
    $this->assertSoftDeleted('produk_varians', ['id' => $varian->id]);
});

test('deleted product does not appear in the list', function () {
    $this->actingAs(User::factory()->create());

    $produk = Produk::factory()
        ->for(Kategori::factory())
        ->create(['nama_produk' => 'Produk Dihapus']);

    ProdukVarian::factory()->for($produk)->for(Satuan::factory())->create();

    $produk->varians()->delete();
    $produk->delete();

    $this->get(route('produk.index'))
        ->assertOk()
        ->assertDontSee('Produk Dihapus');
});
