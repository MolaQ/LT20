<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

// Potwierdzenie e-maila przyciskiem z maila: podpisany link z hashem adresu, działa bez logowania.

function verifyLink(User $user, ?string $email = null): string
{
    return URL::temporarySignedRoute('verification.link', now()->addDay(), ['id' => $user->id, 'hash' => sha1($email ?? $user->email)]);
}

test('the signed link confirms the email without logging in', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verifyLink($user))->assertRedirect(route('login'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a logged in player goes back to the site after the link', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verifyLink($user))->assertRedirect(route('home'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a link for another address or without a valid signature does nothing', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verifyLink($user, 'stary@example.com'))->assertForbidden();
    $this->get(route('verification.link', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
