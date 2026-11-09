<?php

use App\Models\User;

// Wygląd 2.0: podgląd skorupy aplikacji i przełącznik języka.

test('the preview page renders the app shell with the bottom menu', function () {
    $this->get(route('preview'))
        ->assertOk()
        ->assertSee(config('club.brand.prefix').config('club.brand.suffix'))
        ->assertSee('--club-primary', false);
});

test('a guest can switch the language for the session', function () {
    $this->post(route('locale.switch', 'en'))->assertRedirect();

    $this->get(route('preview'))->assertSee('lang="en"', false);
});

test('a logged in player keeps the chosen language in the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('locale.switch', 'pl'))->assertRedirect();

    expect($user->fresh()->locale)->toBe('pl');
});

test('an unsupported language is rejected', function () {
    $this->post(route('locale.switch', 'de'))->assertNotFound();
});

test('the browser language is used when nothing was chosen', function () {
    $this->get(route('preview'), ['Accept-Language' => 'en-GB,en;q=0.9'])->assertSee('lang="en"', false);
});
