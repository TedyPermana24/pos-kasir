<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

test('authenticated users can visit the create promo page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('promo.create'))
        ->assertOk();
});

test('can create a promo with varians', function () {
    $this->actingAs(User::factory()->create());

    $varian = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory()))
        ->for(Satuan::factory())
        ->create(['harga_jual' => 15000]);

    Livewire::test('pages::promo.create')
        ->set('nama', 'Promo Test')
        ->set('tanggal_mulai', '2026-07-01')
        ->set('tanggal_selesai', '2026-07-31')
        ->set('is_active', true)
        ->set('selectedVarians', [
            [
                'produk_varian_id' => $varian->id,
                'produk_nama' => $varian->produk->nama_produk,
                'varian_nama' => $varian->nama_varian,
                'harga_jual' => '15000',
                'minimal_harga_jual' => '12000',
            ],
        ])
        ->call('save')
        ->assertRedirect(route('promo.index'));

    $this->assertDatabaseHas('promos', [
        'nama' => 'Promo Test',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('promo_produk_varian', [
        'produk_varian_id' => $varian->id,
        'minimal_harga_jual' => 12000,
    ]);
});

test('required fields are validated', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::promo.create')
        ->set('nama', '')
        ->set('selectedVarians', [])
        ->call('save')
        ->assertHasErrors(['nama', 'selectedVarians']);
});

test('tanggal_selesai must be after tanggal_mulai', function () {
    $this->actingAs(User::factory()->create());

    $varian = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory()))
        ->for(Satuan::factory())
        ->create();

    Livewire::test('pages::promo.create')
        ->set('nama', 'Promo Test')
        ->set('tanggal_mulai', '2026-08-01')
        ->set('tanggal_selesai', '2026-07-01')
        ->set('selectedVarians', [
            [
                'produk_varian_id' => $varian->id,
                'produk_nama' => 'Test',
                'varian_nama' => 'Default',
                'harga_jual' => '10000',
                'minimal_harga_jual' => '10000',
            ],
        ])
        ->call('save')
        ->assertHasErrors(['tanggal_selesai']);
});

test('can add varian to selected list', function () {
    $this->actingAs(User::factory()->create());

    $varian = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory())->create(['nama_produk' => 'Indomie']))
        ->for(Satuan::factory())
        ->create(['nama_varian' => 'Goreng', 'harga_jual' => 3500]);

    $component = Livewire::test('pages::promo.create')
        ->call('addVarian', $varian->id, 'Indomie', 'Goreng', '3500');

    expect($component->get('selectedVarians'))->toHaveCount(1);
    expect($component->get('selectedVarians.0.produk_varian_id'))->toBe($varian->id);
    expect($component->get('selectedVarians.0.minimal_harga_jual'))->toBe('3500');
});

test('cannot add duplicate varian', function () {
    $this->actingAs(User::factory()->create());

    $varian = ProdukVarian::factory()
        ->for(Produk::factory()->for(Kategori::factory()))
        ->for(Satuan::factory())
        ->create();

    Livewire::test('pages::promo.create')
        ->call('addVarian', $varian->id, 'Test', 'Default', '10000')
        ->call('addVarian', $varian->id, 'Test', 'Default', '10000')
        ->assertNotDispatched('toast');

    // Should still have only 1
    $component = Livewire::test('pages::promo.create')
        ->call('addVarian', $varian->id, 'Test', 'Default', '10000')
        ->call('addVarian', $varian->id, 'Test', 'Default', '10000');

    expect($component->get('selectedVarians'))->toHaveCount(1);
});

test('can remove varian from selected list', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test('pages::promo.create')
        ->set('selectedVarians', [
            [
                'produk_varian_id' => 1,
                'produk_nama' => 'Test',
                'varian_nama' => 'Default',
                'harga_jual' => '10000',
                'minimal_harga_jual' => '10000',
            ],
            [
                'produk_varian_id' => 2,
                'produk_nama' => 'Test 2',
                'varian_nama' => 'Besar',
                'harga_jual' => '15000',
                'minimal_harga_jual' => '12000',
            ],
        ])
        ->call('removeVarian', 0);

    expect($component->get('selectedVarians'))->toHaveCount(1);
    expect($component->get('selectedVarians.0.produk_varian_id'))->toBe(2);
});
