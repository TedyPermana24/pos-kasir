<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Promo;
use App\Models\Satuan;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('promo.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the promo index', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('promo.index'))
        ->assertOk();
});

test('promo list displays promos with varian count', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->active()->create(['nama' => 'Promo Kemerdekaan']);
    $varian = ProdukVarian::factory()->for(Produk::factory()->for(Kategori::factory()))->for(Satuan::factory())->create();
    $promo->produkVarians()->attach($varian->id, ['minimal_harga_jual' => 10000]);

    $this->get(route('promo.index'))
        ->assertOk()
        ->assertSee('Promo Kemerdekaan')
        ->assertSee('1 varian');
});

test('search filters promos by name', function () {
    $this->actingAs(User::factory()->create());

    Promo::factory()->create(['nama' => 'Flash Sale']);
    Promo::factory()->create(['nama' => 'Cuci Gudang']);

    $this->get(route('promo.index', ['q' => 'Flash']))
        ->assertOk()
        ->assertSee('Flash Sale')
        ->assertDontSee('Cuci Gudang');
});

test('can toggle promo active status', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->active()->create(['is_active' => true]);

    Livewire::test('pages::promo.index')
        ->call('toggleActive', $promo->id);

    expect($promo->refresh()->is_active)->toBeFalse();
});

test('can delete a promo', function () {
    $this->actingAs(User::factory()->create());

    $promo = Promo::factory()->create();
    $varian = ProdukVarian::factory()->for(Produk::factory()->for(Kategori::factory()))->for(Satuan::factory())->create();
    $promo->produkVarians()->attach($varian->id, ['minimal_harga_jual' => 5000]);

    Livewire::test('pages::promo.index')
        ->call('confirmDelete', $promo->id)
        ->call('deletePromo')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('promos', ['id' => $promo->id]);
    $this->assertDatabaseMissing('promo_produk_varian', ['promo_id' => $promo->id]);
});

test('status filter shows only active promos', function () {
    $this->actingAs(User::factory()->create());

    Promo::factory()->active()->create(['nama' => 'Promo Aktif']);
    Promo::factory()->inactive()->create(['nama' => 'Promo Nonaktif']);

    Livewire::test('pages::promo.index')
        ->set('statusFilter', 'aktif')
        ->assertSee('Promo Aktif')
        ->assertDontSee('Promo Nonaktif');
});

test('status filter shows only inactive promos', function () {
    $this->actingAs(User::factory()->create());

    Promo::factory()->active()->create(['nama' => 'Promo Aktif']);
    Promo::factory()->inactive()->create(['nama' => 'Promo Nonaktif']);

    Livewire::test('pages::promo.index')
        ->set('statusFilter', 'nonaktif')
        ->assertDontSee('Promo Aktif')
        ->assertSee('Promo Nonaktif');
});
