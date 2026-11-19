<?php

use App\Models\Sponsor;
use App\Models\SponsorClick;

// Licznik kliknięć w blok partnera rozgrywek.

test('a click on the partner block is counted and redirects to the partner', function () {
    $sponsor = Sponsor::create(['name' => 'KKSLech.com', 'url' => 'https://kkslech.com']);

    $this->get(route('sponsor.click', $sponsor))->assertRedirect('https://kkslech.com');
    $this->get(route('sponsor.click', $sponsor))->assertRedirect('https://kkslech.com');
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('sponsor.click', $sponsor))->assertRedirect('https://kkslech.com');

    expect(SponsorClick::sole()->clicks)->toBe(2);
});

test('a partner without a website has no click link', function () {
    $sponsor = Sponsor::create(['name' => 'Bez strony']);

    $this->get(route('sponsor.click', $sponsor))->assertNotFound();
    expect(SponsorClick::count())->toBe(0);
});
