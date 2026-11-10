<?php

use App\Enums\Permission;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Przelewy24Controller;
use App\Support\Sitemap;
use Illuminate\Support\Facades\Route;

// Strona publiczna
Route::livewire('/', 'pages::home.welcome')->name('home');
Route::livewire('/permission', 'pages::home.permissiontest')->name('permission'); // tymczasowy test

// Strefa gracza na stronie głównej (bez panelu admina): typowanie i wyniki.
// Typowanie z przyjaznym adresem: /tips/kolejka-3/statystyki (obie części opcjonalne).
Route::livewire('tips/{matchday_slug?}/{tab_slug?}', 'pages::tips')
    ->where(['matchday_slug' => 'kolejka-[0-9]+', 'tab_slug' => '[a-z-]+'])
    ->middleware(['auth', 'verified'])
    ->name('tips');
// Wyniki z przyjaznym adresem: /results/sezon-2/ekstraklasa/kolejka-9 (wszystkie części opcjonalne).
Route::livewire('results/{season_slug?}/{competition_slug?}/{round_slug?}', 'pages::results')
    ->where(['season_slug' => 'sezon-[0-9]+', 'competition_slug' => '[a-z0-9-]+', 'round_slug' => 'kolejka-[0-9]+'])
    ->name('results');
// Statystyki premium (przebudowa 2.0): forma, serie, odznaki, „co jeśli” i mapa typów.
Route::livewire('my-stats', 'pages::my-stats')->middleware(['auth', 'verified'])->name('my-stats');
Route::livewire('hall-of-fame', 'pages::hall-of-fame')->name('hall-of-fame');
Route::livewire('history/{key_slug?}', 'pages::history')->name('history');
Route::livewire('stars', 'pages::stars')->name('stars');
Route::livewire('teams/{user}', 'pages::team-profile')->name('team.show');
Route::livewire('bots/{bot}', 'pages::bot-profile')->name('bot.show');

// Premium i wsparcie (etap 15): cennik i płatność online, powiadomienia od Przelewy24 (bez CSRF, patrz bootstrap/app.php).
Route::livewire('support', 'pages::support')->name('support');
Route::livewire('rules', 'pages::rules')->name('rules');
Route::livewire('faq', 'pages::faq')->name('faq');
Route::livewire('system', 'pages::system-feed')->name('system');
Route::livewire('news/{news}', 'pages::news-show')->name('news.show');
Route::livewire('office', 'pages::office')->name('office');
// Przełącznik języka (pl, en) z menu „Więcej”.
Route::post('locale/{locale}', LocaleController::class)->name('locale.switch');

// PWA: manifest z konfiguracji klubu i strona bez internetu (service worker: public/sw.js).
Route::get('manifest.webmanifest', ManifestController::class)->name('manifest');
Route::view('offline', 'offline')->name('offline');

// Podgląd nowego wyglądu (przebudowa 2.0): skorupa aplikacji i komponenty bez Flux.
Route::livewire('preview', 'pages::app-preview')->name('preview');
Route::post('payments/przelewy24/status', [Przelewy24Controller::class, 'status'])->name('przelewy24.status');

// Panel: wejście tylko z uprawnieniem dashboard-access (Admin ma je zawsze)
Route::middleware(['auth', 'verified', 'permission:'.Permission::DashboardAccess->value])->group(function () {
    Route::livewire('panel', 'pages::dashboard.stats')
        ->name('dashboard');
    // Zarządzanie rozgrywkami
    Route::livewire('panel/seasons', 'pages::dashboard.seasons')
        ->middleware('permission:'.Permission::SeasonList->value)
        ->name('dashboard.seasons');
    Route::livewire('panel/matchdays', 'pages::dashboard.matchdays')
        ->middleware('permission:'.Permission::SeasonList->value)
        ->name('dashboard.matchdays');
    Route::livewire('panel/lech-calendar', 'pages::dashboard.lech-calendar')
        ->middleware('permission:'.Permission::SeasonList->value)
        ->name('dashboard.lech-calendar');
    Route::livewire('panel/lech-calendar/{lechMatch}/report', 'pages::dashboard.lech-match-report')
        ->middleware('permission:'.Permission::SeasonList->value)
        ->name('dashboard.lech-match-report');
    Route::livewire('panel/season-teams', 'pages::dashboard.season-teams')
        ->middleware('permission:'.Permission::SeasonList->value)
        ->name('dashboard.season-teams');
    Route::livewire('panel/bots', 'pages::dashboard.bots')
        ->middleware('permission:'.Permission::SeasonList->value)
        ->name('dashboard.bots');
    Route::livewire('panel/fixtures', 'pages::dashboard.fixtures')
        ->middleware('permission:season-list')
        ->name('dashboard.fixtures');
    Route::livewire('panel/competitions', 'pages::dashboard.competitions')
        ->middleware('permission:season-list')
        ->name('dashboard.competitions');
    Route::livewire('panel/european-qualification', 'pages::dashboard.european-qualification')
        ->middleware('permission:season-list')
        ->name('dashboard.european-qualification');
    Route::livewire('panel/checklist', 'pages::dashboard.checklist')
        ->middleware('permission:season-list')
        ->name('dashboard.checklist');
    Route::livewire('panel/lech-squad', 'pages::dashboard.lech-squad')
        ->middleware('permission:season-list')
        ->name('dashboard.lech-squad');
    Route::livewire('panel/tips', 'pages::dashboard.tips-overview')
        ->middleware('permission:season-list')
        ->name('dashboard.tips');
    Route::livewire('panel/matchday-wizard', 'pages::dashboard.matchday-wizard')
        ->middleware('permission:season-list')
        ->name('dashboard.matchday-wizard');
    Route::livewire('panel/questions', 'pages::dashboard.questions')
        ->middleware('permission:season-list')
        ->name('dashboard.questions');
    Route::livewire('panel/results', 'pages::dashboard.results')
        ->middleware('permission:season-list')
        ->name('dashboard.results');
    Route::livewire('panel/sponsors', 'pages::dashboard.sponsors')
        ->middleware('permission:season-list')
        ->name('dashboard.sponsors');
    Route::livewire('panel/hall-of-fame', 'pages::dashboard.hall-of-fame')
        ->middleware('permission:season-list')
        ->name('dashboard.hall-of-fame');
    Route::livewire('panel/designation-settings', 'pages::dashboard.designation-settings')
        ->middleware('permission:season-list')
        ->name('dashboard.designation-settings');
    Route::livewire('panel/competition-colors', 'pages::dashboard.competition-colors')
        ->middleware('permission:season-list')
        ->name('dashboard.competition-colors');
    Route::livewire('panel/news', 'pages::dashboard.news')
        ->middleware('permission:'.Permission::NewsCreate->value)
        ->name('dashboard.news');
    // Zarządzanie rolami i użytkownikami: nadal tylko Admin
    Route::livewire('panel/roles', 'pages::dashboard.roles')
        ->middleware('role:Admin')
        ->name('dashboard.roles');

    Route::livewire('panel/users', 'pages::dashboard.users')
        ->middleware('role:Admin')
        ->name('dashboard.users');

    Route::livewire('panel/office', 'pages::dashboard.office')
        ->middleware('role:Admin')
        ->name('dashboard.office');

    Route::livewire('panel/statistics', 'pages::dashboard.site-statistics')
        ->middleware('role:Admin')
        ->name('dashboard.site-statistics');
    Route::livewire('panel/site-colors', 'pages::dashboard.site-colors')
        ->middleware('role:Admin')
        ->name('dashboard.site-colors');
    Route::livewire('panel/maintenance', 'pages::dashboard.maintenance')
        ->middleware('role:Admin')
        ->name('dashboard.maintenance');

    Route::livewire('panel/payments', 'pages::dashboard.payments')
        ->middleware('role:Admin')
        ->name('dashboard.payments');

    Route::livewire('panel/team-requests', 'pages::dashboard.team-requests')
        ->middleware('permission:'.Permission::TeamChangeName->value)
        ->name('dashboard.team-requests');

    Route::livewire('panel/logs', 'pages::dashboard.logs')
        ->middleware('permission:'.Permission::LogView->value)
        ->name('dashboard.logs');
});

// Wyszukiwarki: robots.txt z adresem mapy strony i mapa stron publicznych (App\Support\Sitemap).
Route::get('robots.txt', fn () => response(Sitemap::robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']))->name('robots');
Route::get('sitemap.xml', fn () => response(Sitemap::xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']))->name('sitemap');

// Stare adresy panelu (/dashboard/...) prowadzą do nowych (/panel/...); nazwy tras zostały dashboard.*.
Route::get('dashboard/{path?}', fn (?string $path = null) => redirect(url('panel'.($path ? '/'.$path : '')), 301))
    ->where('path', '.*');

require __DIR__.'/settings.php';
