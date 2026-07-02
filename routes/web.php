<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('permission:produk.view')->group(function () {
        Route::livewire('produk', 'pages::produk.index')->name('produk.index');
    });
    Route::middleware('permission:produk.create')->group(function () {
        Route::livewire('produk/create', 'pages::produk.create')->name('produk.create');
    });
    Route::middleware('permission:produk.edit')->group(function () {
        Route::livewire('produk/{produk}/edit', 'pages::produk.edit')->name('produk.edit');
    });

    Route::middleware('permission:promo.view')->group(function () {
        Route::livewire('promo', 'pages::promo.index')->name('promo.index');
    });
    Route::middleware('permission:promo.create')->group(function () {
        Route::livewire('promo/create', 'pages::promo.create')->name('promo.create');
    });
    Route::middleware('permission:promo.edit')->group(function () {
        Route::livewire('promo/{promo}/edit', 'pages::promo.edit')->name('promo.edit');
    });

    Route::middleware('permission:pajak.manage')->group(function () {
        Route::livewire('pajak', 'pages::pajak.index')->name('pajak.index');
    });

    Route::middleware('permission:pegawai.manage')->group(function () {
        Route::livewire('jabatan', 'pages::jabatan.index')->name('jabatan.index');
        Route::livewire('jabatan/create', 'pages::jabatan.create')->name('jabatan.create');
        Route::livewire('jabatan/{jabatan}/edit', 'pages::jabatan.edit')->name('jabatan.edit');

        Route::livewire('pegawai', 'pages::pegawai.index')->name('pegawai.index');
        Route::livewire('pegawai/create', 'pages::pegawai.create')->name('pegawai.create');
        Route::livewire('pegawai/{user}/edit', 'pages::pegawai.edit')->name('pegawai.edit');
    });

    Route::middleware('permission:transaksi.create')->group(function () {
        Route::livewire('kasir', 'pages::transaksi.index')->name('kasir.index');
        Route::livewire('kasir/riwayat', 'pages::transaksi.riwayat')->name('kasir.riwayat');
    });
});

require __DIR__.'/settings.php';
