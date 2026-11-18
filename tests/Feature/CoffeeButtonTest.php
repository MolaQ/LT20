<?php

use Illuminate\Support\Facades\Blade;

// Przyciski „Postaw kawkę” (18.11.2026): prosto na buycoffee.to, bez adresu na stronę wsparcia; premium tylko na stronie wsparcia.

test('the coffee button goes straight to buycoffee.to in a new tab', function () {
    config(['services.buycoffee.url' => 'https://buycoffee.to/lechtyper']);

    foreach (['pill', 'card', 'banner', 'tile'] as $variant) {
        $html = Blade::render('<x-coffee-button :variant="$variant" />', ['variant' => $variant]);

        expect($html)->toContain('href="https://buycoffee.to/lechtyper"')
            ->and($html)->toContain('target="_blank"')
            ->and($html)->toContain(__('Buy us a coffee'));
    }
});

test('without a buycoffee.to address the coffee button opens the support page', function () {
    config(['services.buycoffee.url' => null]);

    expect(Blade::render('<x-coffee-button />'))->toContain('href="'.route('support').'"');
});

test('the side panel shows the coffee card instead of the premium card', function () {
    config(['services.buycoffee.url' => 'https://buycoffee.to/lechtyper']);

    expect(Blade::render('<x-public.right-panel />'))->toContain(__('Do you like LechTYPER?'))
        ->and(Blade::render('<x-public.right-panel />'))->not->toContain(__('Rival preview: their outcome while tipping is open, the exact tip after it closes'));
});
