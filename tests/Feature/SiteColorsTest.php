<?php

use App\Support\SiteColors;

// Kolory strony z panelu: zapis tylko zmienionych, zmienne CSS dla całej strony i jasnego motywu.

test('only changed colors are stored and override the defaults', function () {
    $colors = SiteColors::defaults();
    $colors['flap_bg'] = '#0B4EA2';

    SiteColors::save($colors);

    expect(SiteColors::saved())->toBe(['flap_bg' => '#0b4ea2'])
        ->and(SiteColors::all()['flap_bg'])->toBe('#0b4ea2')
        ->and(SiteColors::all()['primary'])->toBe(strtolower(config('club.colors.primary')));
});

test('light theme colors go only to the light theme', function () {
    $css = SiteColors::css();

    expect($css[':root'])->toContain('--flap-bg: #1d1f24;')->not->toContain('--ui-bg')
        ->and($css[':root:not(.dark)'])->toContain('--ui-bg: #f3f5f9;');
});

test('invalid colors are ignored when saving', function () {
    SiteColors::save(['bar_ink' => 'red', 'bar_from' => '#123456']);

    expect(SiteColors::saved())->toBe(['bar_from' => '#123456']);
});
