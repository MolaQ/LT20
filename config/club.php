<?php

/*
 * Konfiguracja klubu: jedna instalacja = jeden klub.
 * Wdrożenie dla innej drużyny zmienia tylko wartości w .env (i logo w public/images).
 */
return [
    // Pełna nazwa klubu, np. „Lech Poznań”.
    'name' => env('CLUB_NAME', 'Lech Poznań'),

    // Krótka nazwa do wyników i tabel, np. „Lech”.
    'short_name' => env('CLUB_SHORT_NAME', 'Lech'),

    // Nazwa gry: pierwsza część (klub) i druga (wyróżniona), razem #LechTYPER.
    'brand' => [
        'prefix' => env('CLUB_BRAND_PREFIX', '#Lech'),
        'suffix' => env('CLUB_BRAND_SUFFIX', 'TYPER'),
    ],

    // Logo gry (ścieżka w public).
    'logo' => env('CLUB_LOGO', 'images/lechtyper-logo.png'),

    // Barwy motywu. primary = barwa klubu, deep = tło ciemnego motywu, accent = wyróżnienia (punkty, premium).
    'colors' => [
        'primary' => env('CLUB_COLOR_PRIMARY', '#0b4ea2'),
        'primary_dark' => env('CLUB_COLOR_PRIMARY_DARK', '#5b9bff'),
        'deep' => env('CLUB_COLOR_DEEP', '#071228'),
        'accent' => env('CLUB_COLOR_ACCENT', '#f0c45a'),
    ],

    // Języki serwisu. Pierwszy z listy jest domyślny dla gości bez pasującego języka przeglądarki.
    'locales' => ['pl' => 'Polski', 'en' => 'English'],
];
