<?php

use App\Models\Jabatan;
use App\Models\Kategori;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // Setup Admin with laporan.view, laporan.omzet, and transaksi.cancel permissions
    $this->admin = User::factory()->create();
    $adminJabatan = Jabatan::create(['nama' => 'Admin Test']);
    $laporanViewPerm = Permission::firstOrCreate(['nama' => 'laporan.view']);
    $laporanOmzetPerm = Permission::firstOrCreate(['nama' => 'laporan.omzet']);
    $transaksiCancelPerm = Permission::firstOrCreate(['nama' => 'transaksi.cancel']);
    $adminJabatan->permissions()->sync([$laporanViewPerm->id, $laporanOmzetPerm->id, $transaksiCancelPerm->id]);
    $this->admin->update(['jabatan_id' => $adminJabatan->id]);

    // Setup Kasir without laporan.view
    $this->kasir = User::factory()->create();
    $kasirJabatan = Jabatan::create(['nama' => 'Kasir Test']);
    $this->kasir->update(['jabatan_id' => $kasirJabatan->id]);
});

it('restricts access to laporan transaksi for users without permission', function () {
    $this->actingAs($this->kasir)
        ->get(route('laporan.transaksi'))
        ->assertForbidden();
});

it('allows access to laporan transaksi for users with permission', function () {
    $this->actingAs($this->admin)
        ->get(route('laporan.transaksi'))
        ->assertOk()
        ->assertSee('Laporan Transaksi');
});

it('filters transaksi by date correctly', function () {
    Transaksi::factory()->create(['created_at' => Carbon::today()->subDays(5)]);
    $transaksiBaru = Transaksi::factory()->create(['created_at' => Carbon::today()]);

    Livewire::actingAs($this->admin)
        ->test('pages::laporan.transaksi')
        ->set('startDate', Carbon::today()->format('Y-m-d'))
        ->set('endDate', Carbon::today()->format('Y-m-d'))
        ->assertSee($transaksiBaru->no_referensi)
        ->assertDontSee(Transaksi::whereDate('created_at', '<', Carbon::today())->first()->no_referensi ?? 'xxxxx');
});

it('supports date range shortcuts', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::laporan.transaksi')
        ->call('filterHariIni')
        ->assertSet('startDate', Carbon::today()->format('Y-m-d'))
        ->assertSet('endDate', Carbon::today()->format('Y-m-d'))
        ->call('filterMingguIni')
        ->assertSet('startDate', Carbon::now()->startOfWeek()->format('Y-m-d'))
        ->assertSet('endDate', Carbon::now()->endOfWeek()->format('Y-m-d'))
        ->call('filterBulanIni')
        ->assertSet('startDate', Carbon::now()->startOfMonth()->format('Y-m-d'))
        ->assertSet('endDate', Carbon::now()->endOfMonth()->format('Y-m-d'));
});

it('shows omzet only to users with laporan.omzet permission', function () {
    Transaksi::factory()->create(['grand_total' => 150000, 'created_at' => Carbon::today(), 'status' => 'selesai']);

    // Admin has laporan.omzet permission
    Livewire::actingAs($this->admin)
        ->test('pages::laporan.transaksi')
        ->assertSee('Total Omzet Selesai')
        ->assertSee('Rp 150.000');

    // Create user with laporan.view ONLY (no laporan.omzet)
    $staff = User::factory()->create();
    $staffJabatan = Jabatan::create(['nama' => 'Staff Test']);
    $laporanViewPerm = Permission::firstOrCreate(['nama' => 'laporan.view']);
    $staffJabatan->permissions()->sync([$laporanViewPerm->id]);
    $staff->update(['jabatan_id' => $staffJabatan->id]);

    Livewire::actingAs($staff)
        ->test('pages::laporan.transaksi')
        ->assertDontSee('Total Omzet Selesai');
});

it('can cancel transaction in riwayat kasir and restore product stock', function () {
    $kategori = Kategori::factory()->create();
    $satuan = Satuan::factory()->create();
    $produk = Produk::factory()->create(['kategori_id' => $kategori->id]);
    $varian = ProdukVarian::factory()->create([
        'produk_id' => $produk->id,
        'satuan_id' => $satuan->id,
        'stok' => 5,
    ]);

    $transaksi = Transaksi::factory()->create([
        'user_id' => $this->admin->id,
        'status' => 'selesai',
        'created_at' => Carbon::today(),
    ]);

    TransaksiDetail::create([
        'transaksi_id' => $transaksi->id,
        'produk_varian_id' => $varian->id,
        'kuantitas' => 3,
        'harga_satuan' => 10000,
        'harga_modal' => 5000,
        'subtotal' => 30000,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::transaksi.riwayat')
        ->call('confirmCancel', $transaksi->id)
        ->set('alasanPembatalan', 'Salah input barang')
        ->call('processCancel');

    $transaksi->refresh();
    expect($transaksi->status)->toBe('dibatalkan')
        ->and($transaksi->alasan_pembatalan)->toBe('Salah input barang')
        ->and($transaksi->cancelled_by_user_id)->toBe($this->admin->id)
        ->and($varian->fresh()->stok)->toBe(8); // 5 + 3 = 8
});

it('can export transaksi to csv', function () {
    Transaksi::factory(3)->create();

    Livewire::actingAs($this->admin)
        ->test('pages::laporan.transaksi')
        ->call('exportCsv')
        ->assertFileDownloaded();
});
