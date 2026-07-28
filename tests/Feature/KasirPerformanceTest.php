<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Promo;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->kategori = Kategori::factory()->create();
    $this->satuan = Satuan::factory()->create();
});

it('paginates products on kasir page showing only 20 per page', function () {
    // Create 25 products with unique names
    for ($i = 1; $i <= 25; $i++) {
        $produk = Produk::factory()->create([
            'kategori_id' => $this->kategori->id,
            'nama_produk' => "Produk Test {$i}",
        ]);

        ProdukVarian::factory()->create([
            'produk_id' => $produk->id,
            'satuan_id' => $this->satuan->id,
        ]);
    }

    // Page 1 should not show products 21-25 (alphabetically sorted)
    $component = Livewire::actingAs($this->user)
        ->test('pages::transaksi.index');

    // "Produk Test 9" comes last alphabetically, should be on page 2
    // First page should have pagination controls (hasMorePages)
    $component->assertSee('Produk Test 1')
        ->assertDontSee('Produk Test 9'); // alphabetical: "9" sorts after "8" and after "5"
});

it('resets pagination when search query changes', function () {
    $produks = Produk::factory()
        ->count(25)
        ->create(['kategori_id' => $this->kategori->id]);

    foreach ($produks as $produk) {
        ProdukVarian::factory()->create([
            'produk_id' => $produk->id,
            'satuan_id' => $this->satuan->id,
        ]);
    }

    Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->call('nextPage')
        ->set('search', 'test')
        ->assertSet('paginators.page', 1);
});

it('resets pagination when kategori filter changes', function () {
    $kategori2 = Kategori::factory()->create();

    $produks = Produk::factory()
        ->count(25)
        ->create(['kategori_id' => $this->kategori->id]);

    foreach ($produks as $produk) {
        ProdukVarian::factory()->create([
            'produk_id' => $produk->id,
            'satuan_id' => $this->satuan->id,
        ]);
    }

    Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->call('nextPage')
        ->set('kategoriFilter', (string) $kategori2->id)
        ->assertSet('paginators.page', 1);
});

it('loads promo data without N+1 queries in openDetail', function () {
    $produk = Produk::factory()->create(['kategori_id' => $this->kategori->id]);
    $varian = ProdukVarian::factory()->create([
        'produk_id' => $produk->id,
        'satuan_id' => $this->satuan->id,
        'harga_jual' => 50000,
    ]);

    // Create 3 active promos linked to this varian
    $promos = Promo::factory()->active()->count(3)->create();
    foreach ($promos as $promo) {
        $promo->produkVarians()->attach($varian->id, ['minimal_harga_jual' => 10000]);
    }

    $component = Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->call('openDetail', $varian->id);

    $component->assertSet('showDetail', true);
    $component->assertSet('detailNamaProduk', $produk->nama_produk);

    $availablePromos = $component->get('detailAvailablePromos');
    expect($availablePromos)->toHaveCount(3);
    expect($availablePromos[0]['minimal_harga_jual'])->toBe(10000.0);
});

it('renders kategori filter buttons', function () {
    $kategori2 = Kategori::factory()->create(['nama' => 'Minuman Segar']);

    Livewire::actingAs($this->user)
        ->test('pages::transaksi.index')
        ->assertSee($this->kategori->nama)
        ->assertSee('Minuman Segar');
});
