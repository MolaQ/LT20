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

test('the web app manifest uses the club configuration', function () {
    $this->get(route('manifest'))
        ->assertOk()
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('name', config('club.brand.prefix').config('club.brand.suffix'))
        ->assertJsonPath('theme_color', config('club.colors.deep'));
});

test('the offline page renders', function () {
    $this->get(route('offline'))->assertOk()->assertSee(__('No connection'));
});

test('public pages use the new shell with the bottom menu', function () {
    $this->get(route('rules'))
        ->assertOk()
        ->assertSee('ui-shell', false)
        ->assertSee(route('manifest'), false);
});

test('recalculation progress is stored and cleared', function () {
    \App\Support\RecalcProgress::set(99, 40, 'Scoring');

    expect(\App\Support\RecalcProgress::get(99))->toMatchArray(['percent' => 40, 'label' => 'Scoring', 'status' => 'running']);

    \App\Support\RecalcProgress::forget(99);

    expect(\App\Support\RecalcProgress::get(99))->toBeNull();
});
