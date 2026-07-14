<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('staff:create creates a verified user that can log in', function () {
    $this->artisan('staff:create')
        ->expectsQuestion('Имя', 'Иван Иванов')
        ->expectsQuestion('Email', 'ivan@rudagames.team')
        ->expectsQuestion('Пароль', 'a-very-secure-password')
        ->assertExitCode(0);

    $user = User::where('email', 'ivan@rudagames.team')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Иван Иванов');
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('a-very-secure-password', $user->password))->toBeTrue();
});

test('staff:create rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@rudagames.team']);

    $this->artisan('staff:create')
        ->expectsQuestion('Имя', 'Пётр Петров')
        ->expectsQuestion('Email', 'taken@rudagames.team')
        ->expectsQuestion('Пароль', 'another-secure-password')
        ->assertExitCode(1);

    expect(User::where('email', 'taken@rudagames.team')->count())->toBe(1);
});

test('public registration is disabled', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});
