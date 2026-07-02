<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->withPin('123456')->create();

    $response = $this->post(route('login.store'), [
        'name' => $user->name,
        'password' => '123456',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->withPin('123456')->create();

    $response = $this->post(route('login.store'), [
        'name' => $user->name,
        'password' => 'wrong-pin',
    ]);

    $response->assertSessionHasErrors();

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
