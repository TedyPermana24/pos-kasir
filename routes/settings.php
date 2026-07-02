<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/appearance');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::middleware('permission:outlet.manage')->group(function () {
        Route::livewire('settings/outlet', 'pages::settings.outlet')->name('outlet.edit');
    });
});
