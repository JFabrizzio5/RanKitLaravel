<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('actualiza nombre y teléfono; el teléfono se guarda solo con dígitos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', ['name' => 'Test User', 'phone' => '(55) 1234-5678'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();
    expect($user->name)->toBe('Test User')
        ->and($user->phone)->toBe('5512345678');
});

test('el teléfono es obligatorio y de 10 a 15 dígitos', function (?string $telefono) {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')
        ->patch('/profile', ['name' => 'Test User', 'phone' => $telefono])
        ->assertSessionHasErrors('phone');
})->with([null, '', '12345', '55-1234', '+1234567890123456']);

test('acepta lada internacional con +', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', ['name' => 'Test User', 'phone' => '+1 (415) 555-0100'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->phone)->toBe('+14155550100');
});

test('el correo no se puede cambiar desde el perfil', function () {
    $user = User::factory()->create();
    $original = $user->email;

    $this->actingAs($user)
        ->patch('/profile', ['name' => 'Test User', 'email' => 'otro@example.com', 'phone' => '5512345678'])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->email)->toBe($original)
        ->and($user->email_verified_at)->not->toBeNull();
});

test('la cuenta no se puede borrar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
    $this->assertAuthenticated();
});
