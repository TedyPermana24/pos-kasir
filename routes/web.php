<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('produk', 'pages::produk.index')->name('produk.index');
    Route::livewire('produk/create', 'pages::produk.create')->name('produk.create');
    Route::livewire('produk/{produk}/edit', 'pages::produk.edit')->name('produk.edit');
});

require __DIR__.'/settings.php';
