<?php

use App\Models\Jabatan;
use App\Models\Permission;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('jabatan.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the jabatan index', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('jabatan.index'))
        ->assertOk();
});

test('can create a jabatan with permissions', function () {
    $this->actingAs(User::factory()->create());

    $permission1 = Permission::create(['nama' => 'test.view']);
    $permission2 = Permission::create(['nama' => 'test.create']);

    Livewire::test('pages::jabatan.create')
        ->set('nama', 'Manager')
        ->set('selectedPermissions', [(string) $permission1->id, (string) $permission2->id])
        ->call('save')
        ->assertRedirect(route('jabatan.index'));

    $this->assertDatabaseHas('jabatans', ['nama' => 'Manager']);

    $jabatan = Jabatan::where('nama', 'Manager')->first();
    expect($jabatan->permissions)->toHaveCount(2);
});

test('can update a jabatan', function () {
    $this->actingAs(User::factory()->create());

    $jabatan = Jabatan::create(['nama' => 'Old Name']);
    $permission = Permission::create(['nama' => 'test.view']);

    Livewire::test('pages::jabatan.edit', ['jabatan' => $jabatan])
        ->set('nama', 'New Name')
        ->set('selectedPermissions', [(string) $permission->id])
        ->call('save')
        ->assertRedirect(route('jabatan.index'));

    expect($jabatan->refresh()->nama)->toBe('New Name');
    expect($jabatan->permissions)->toHaveCount(1);
});

test('can delete a jabatan if not used by users', function () {
    $this->actingAs(User::factory()->create());

    $jabatan = Jabatan::create(['nama' => 'To Delete']);

    Livewire::test('pages::jabatan.index')
        ->call('confirmDelete', $jabatan->id)
        ->call('deleteJabatan')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('jabatans', ['id' => $jabatan->id]);
});

test('cannot delete a jabatan if used by users', function () {
    $this->actingAs(User::factory()->create());

    $jabatan = Jabatan::create(['nama' => 'Used Jabatan']);
    User::factory()->create(['jabatan_id' => $jabatan->id]);

    Livewire::test('pages::jabatan.index')
        ->call('confirmDelete', $jabatan->id)
        ->call('deleteJabatan');

    $this->assertDatabaseHas('jabatans', ['id' => $jabatan->id]);
});
