<?php

test('redirects to login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
