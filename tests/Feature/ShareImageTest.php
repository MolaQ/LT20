<?php

use App\Enums\PlayerPosition;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

// Udostępnianie obrazka (18.11.2026): każdy kanał dostaje podpis „#LechTYPER lechtyper.pl”, w podglądzie jest kopiowanie
// obrazu z tekstem. Wspólna paleta pozycji: B bursztynowy, O morski, P indygo, N różowy.

test('the share button passes the hashtag and site address as the caption', function () {
    config(['premium.free' => true, 'club.hashtag' => 'LechTYPER', 'app.url' => 'https://lechtyper.pl']);
    $this->actingAs(User::factory()->create());

    $html = Blade::render('<x-share-image title="Gablota" />');

    expect($html)->toContain('#LechTYPER lechtyper.pl');
});

test('guests do not see the share button', function () {
    expect(Blade::render('<x-share-image title="Gablota" />'))->not->toContain('shareImage');
});

test('every position has its own colour from the shared palette', function () {
    $tones = PlayerPosition::tones();

    expect($tones)->toHaveKeys(PlayerPosition::values())
        ->and($tones['goalkeeper'])->toContain('amber')
        ->and($tones['defender'])->toContain('teal')
        ->and($tones['midfielder'])->toContain('indigo')
        ->and($tones['forward'])->toContain('pink')
        ->and(array_unique($tones))->toHaveCount(4);
});
