<?php

use App\Actions\Produk\ImportProdukAction;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('action imports csv rows with null defaults for optional empty fields', function () {
    $action = new ImportProdukAction;

    $rows = [
        [
            'nama_produk' => 'Kopi Gula Aren',
            'kategori' => 'Minuman',
            'nama_varian' => 'Large',
            'satuan' => 'Cup',
            'harga_jual' => '20000',
            'sku' => '',
            'harga_modal' => '',
            'stok' => '',
            'minimum_stok' => '',
        ],
    ];

    $result = $action->execute($rows);

    expect($result['success'])->toBe(1);
    expect($result['failed'])->toBe(0);

    $produk = Produk::where('nama_produk', 'Kopi Gula Aren')->first();
    expect($produk)->not->toBeNull();
    expect($produk->kategori->nama)->toBe('Minuman');

    $varian = ProdukVarian::where('produk_id', $produk->id)->first();
    expect($varian)->not->toBeNull();
    expect($varian->nama_varian)->toBe('Large');
    expect($varian->satuan->nama)->toBe('Cup');
    expect((float) $varian->harga_jual)->toBe(20000.0);
    expect($varian->sku)->toBeNull();
    expect($varian->harga_modal)->toBeNull();
    expect($varian->stok)->toBeNull();
    expect($varian->minimum_stok)->toBeNull();
});

test('can import product csv via livewire component', function () {
    Storage::fake('tmp-for-tests');
    $user = User::factory()->create();
    $this->actingAs($user);

    $csvContent = "nama_produk,kategori,nama_varian,satuan,harga_jual,sku,harga_modal,stok,minimum_stok\n"
        ."Teh Manis,Minuman,Default,Gelasa,5000,TEH-01,2000,10,2\n"
        ."Es Jeruk,Minuman,Default,Gelasa,7000,,,,,\n";

    $file = UploadedFile::fake()->createWithContent('produk_import.csv', $csvContent);

    Livewire::test('pages::produk.index')
        ->set('importFile', $file)
        ->call('import')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('produks', ['nama_produk' => 'Teh Manis']);
    $this->assertDatabaseHas('produks', ['nama_produk' => 'Es Jeruk']);

    $esJerukVarian = Produk::where('nama_produk', 'Es Jeruk')->first()->varians()->first();
    expect($esJerukVarian->sku)->toBeNull();
    expect($esJerukVarian->harga_modal)->toBeNull();
    expect($esJerukVarian->stok)->toBeNull();
    expect($esJerukVarian->minimum_stok)->toBeNull();
});

test('can download product import csv template', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::produk.index')
        ->call('downloadTemplateCsv')
        ->assertFileDownloaded('template_import_produk.csv');
});

test('can download product import xlsx template', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::produk.index')
        ->call('downloadTemplateXlsx')
        ->assertFileDownloaded('template_import_produk.xlsx');
});

test('imports multiple variants for the same product', function () {
    $action = new ImportProdukAction;

    $rows = [
        [
            'nama_produk' => 'Kopi Latte',
            'kategori' => 'Minuman',
            'nama_varian' => 'Sedang',
            'satuan' => 'Cup',
            'harga_jual' => '15000',
            'sku' => 'LAT-MED',
        ],
        [
            'nama_produk' => 'Kopi Latte',
            'kategori' => 'Minuman',
            'nama_varian' => 'Besar',
            'satuan' => 'Cup',
            'harga_jual' => '18000',
            'sku' => 'LAT-LRG',
        ],
    ];

    $result = $action->execute($rows);

    expect($result['success'])->toBe(2);

    $produk = Produk::where('nama_produk', 'Kopi Latte')->first();
    expect($produk)->not->toBeNull();
    expect($produk->varians()->count())->toBe(2);

    $varians = $produk->varians->pluck('nama_varian')->toArray();
    expect($varians)->toContain('Sedang', 'Besar');
});
