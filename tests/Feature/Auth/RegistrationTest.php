<?php

test('registration screen does NOT exist', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
});

test('direct users registeration does NOT exist', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(404);
});
