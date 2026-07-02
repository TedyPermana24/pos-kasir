<?php

use App\Models\Jabatan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('pegawai.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the pegawai index', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('pegawai.index'))
        ->assertOk();
});

test('can create a pegawai with pin', function () {
    $this->actingAs(User::factory()->create());
    $jabatan = Jabatan::create(['nama' => 'Kasir']);

    Livewire::test('pages::pegawai.create')
        ->set('name', 'Budi')
        ->set('email', 'budi@test.com')
        ->set('jabatan_id', $jabatan->id)
        ->set('pin', '123456')
        ->set('pin_confirmation', '123456')
        ->call('save')
        ->assertRedirect(route('pegawai.index'));

    $this->assertDatabaseHas('users', [
        'name' => 'Budi',
        'email' => 'budi@test.com',
        'jabatan_id' => $jabatan->id,
    ]);

    $budi = User::where('name', 'Budi')->first();
    expect(Hash::check('123456', $budi->pin))->toBeTrue();
});

test('can update a pegawai without changing pin', function () {
    $this->actingAs(User::factory()->create());
    $user = User::factory()->withPin('654321')->create(['name' => 'Old Name']);

    Livewire::test('pages::pegawai.edit', ['user' => $user])
        ->set('name', 'New Name')
        ->call('save')
        ->assertRedirect(route('pegawai.index'));

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect(Hash::check('654321', $user->pin))->toBeTrue(); // Pin not changed
});

test('can update a pegawai and change pin', function () {
    $this->actingAs(User::factory()->create());
    $user = User::factory()->withPin('111111')->create();

    Livewire::test('pages::pegawai.edit', ['user' => $user])
        ->set('pin', '222222')
        ->set('pin_confirmation', '222222')
        ->call('save')
        ->assertRedirect(route('pegawai.index'));

    $user->refresh();
    expect(Hash::check('222222', $user->pin))->toBeTrue();
});

test('cannot delete self', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::pegawai.index')
        ->call('confirmDelete', $user->id)
        ->call('deletePegawai');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('can delete other pegawai', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $pegawai = User::factory()->create();

    Livewire::test('pages::pegawai.index')
        ->call('confirmDelete', $pegawai->id)
        ->call('deletePegawai')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('users', ['id' => $pegawai->id]);
});
