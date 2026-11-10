<?php

use App\Models\PageView;
use App\Support\SiteStats;

// Odsłony stron do licznika w stopce: zapis zwykłych wejść, bez robotów i panelu, bez IP.

test('a page visit is stored without the ip address', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0)')->get(route('rules'))->assertOk();

    $view = PageView::sole();
    expect($view->path)->toBe('/rules')
        ->and($view->visitor_hash)->toHaveLength(64)
        ->and($view->visitor_hash)->not->toContain('127.0.0.1');
});

test('bots are not counted', function () {
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('rules'))->assertOk();

    expect(PageView::count())->toBe(0);
});

test('the counter shows at least the current visitor', function () {
    expect(SiteStats::visits())->toBe(['online' => 1, 'today' => 1, 'visits' => 1, 'total' => 0]);
});

test('the admin sees the visit statistics page', function () {
    \Spatie\Permission\Models\Role::findOrCreate(\App\Enums\RoleName::Admin->value, 'web');
    $admin = \App\Models\User::factory()->create();
    $admin->assignRole(\App\Enums\RoleName::Admin->value);
    PageView::create(['path' => '/rules', 'route_name' => 'rules', 'visitor_hash' => str_repeat('a', 64), 'device' => 'mobile']);

    $this->actingAs($admin)->get(route('dashboard.site-statistics'))
        ->assertOk()
        ->assertSee('/rules');
});

test('players cannot open the visit statistics', function () {
    $this->actingAs(\App\Models\User::factory()->create())->get(route('dashboard.site-statistics'))->assertRedirect();
});
