<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Promo;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

test('authenticated users can visit the edit promo page', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->create();

    $this->get(route('promo.edit', $promo))
        ->assertOk();
});

test('edit form is pre-filled with existing data', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->active()->create([
        'nama' => 'Promo Existing',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2026-07-31',
    ]);

    $varian = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory()))
        ->for(Satuan::factory())
        ->create(['harga_jual' => 20000]);

    $promo->produkVarians()->attach($varian->id, ['minimal_harga_jual' => 18000]);

    $component = Livewire::test('pages::promo.edit', ['promo' => $promo])
        ->assertSet('nama', 'Promo Existing')
        ->assertSet('is_active', true);

    expect($component->get('selectedVarians'))->toHaveCount(1);
    expect($component->get('selectedVarians.0.minimal_harga_jual'))->toBe('18000.00');
});

test('can update promo data', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->create(['nama' => 'Promo Lama']);
    $varian = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory()))
        ->for(Satuan::factory())
        ->create(['harga_jual' => 15000]);

    $promo->produkVarians()->attach($varian->id, ['minimal_harga_jual' => 12000]);

    Livewire::test('pages::promo.edit', ['promo' => $promo])
        ->set('nama', 'Promo Baru')
        ->set('selectedVarians.0.minimal_harga_jual', '13000')
        ->call('save')
        ->assertRedirect(route('promo.index'));

    expect($promo->refresh()->nama)->toBe('Promo Baru');
    expect($promo->produkVarians->first()->pivot->minimal_harga_jual)->toBe('13000.00');
});

test('can add new varian to existing promo', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->create();
    $varian1 = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Produk A']))
        ->for(Satuan::factory())
        ->create(['harga_jual' => 10000]);

    $promo->produkVarians()->attach($varian1->id, ['minimal_harga_jual' => 8000]);

    $varian2 = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Produk B']))
        ->for(Satuan::factory())
        ->create(['harga_jual' => 20000]);

    $component = Livewire::test('pages::promo.edit', ['promo' => $promo])
        ->call('addVarian', $varian2->id, 'Produk B', $varian2->nama_varian, '20000');

    expect($component->get('selectedVarians'))->toHaveCount(2);
});

test('can remove varian from existing promo', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->create();

    $varian1 = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory()))
        ->for(Satuan::factory())
        ->create();
    $varian2 = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory()))
        ->for(Satuan::factory())
        ->create();

    $promo->produkVarians()->attach([
        $varian1->id => ['minimal_harga_jual' => 5000],
        $varian2->id => ['minimal_harga_jual' => 8000],
    ]);

    Livewire::test('pages::promo.edit', ['promo' => $promo])
        ->call('removeVarian', 0)
        ->call('save')
        ->assertRedirect(route('promo.index'));

    expect($promo->refresh()->produkVarians)->toHaveCount(1);
});

test('required fields are validated on edit', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->create();

    Livewire::test('pages::promo.edit', ['promo' => $promo])
        ->set('nama', '')
        ->set('selectedVarians', [])
        ->call('save')
        ->assertHasErrors(['nama', 'selectedVarians']);
});
