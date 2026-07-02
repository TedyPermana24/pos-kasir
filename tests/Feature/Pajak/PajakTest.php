<?php

use App\Models\Pajak;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('pajak.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the pajak index', function () {
    $this->actingAs(User::factory()->create());

    // Seeder should ensure the record exists, but for tests we might need to create it manually
    // if DatabaseSeeder is not run before tests. But Livewire component has firstOrCreate.

    $this->get(route('pajak.index'))
        ->assertOk()
        ->assertSee('Pengaturan Pajak');
});

test('livewire component automatically creates a row if not exists', function () {
    $this->actingAs(User::factory()->create());

    $this->assertDatabaseEmpty('pajaks');

    Livewire::test('pages::pajak.index');

    $this->assertDatabaseHas('pajaks', [
        'nama' => 'PPN',
        'persentase' => 11.00,
        'is_active' => false,
    ]);
});

test('can update pajak settings', function () {
    $this->actingAs(User::factory()->create());

    $pajak = Pajak::create([
        'nama' => 'PPN Lama',
        'persentase' => 10.00,
        'is_active' => false,
    ]);

    Livewire::test('pages::pajak.index')
        ->set('nama', 'PPN Baru')
        ->set('persentase', '12.5')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pajaks', [
        'nama' => 'PPN Baru',
        'persentase' => 12.50,
        'is_active' => true,
    ]);
});

test('validation prevents invalid input', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::pajak.index')
        ->set('nama', '')
        ->set('persentase', '-5')
        ->call('save')
        ->assertHasErrors(['nama', 'persentase']);
});
