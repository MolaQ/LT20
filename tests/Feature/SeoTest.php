<?php

use App\Models\News;
use App\Support\Seo;

// Wyszukiwarki i podgląd linków na X: opis, karta X z obrazkiem, robots.txt i mapa strony.

test('public pages have a description and an x card with an image', function () {
    $this->get(route('rules'))
        ->assertOk()
        ->assertSee('<meta name="description"', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee(asset(Seo::DEFAULT_IMAGE), false);
});

test('a news link preview uses its text', function () {
    $news = News::create(['title' => 'Nowy sezon', 'body' => '<p>Startujemy w piątek o 20:30.</p>', 'published_at' => now()->subHour()]);

    $this->get(route('news.show', $news))
        ->assertOk()
        ->assertSee('<meta property="og:description" content="Startujemy w piątek o 20:30.">', false);
});

test('robots.txt points to the sitemap and hides the panel', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /panel')
        ->assertSee('Sitemap: '.route('sitemap'));
});

test('the sitemap lists public pages and news', function () {
    $news = News::create(['title' => 'Mapa', 'body' => 'Tekst', 'published_at' => now()->subHour()]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(route('rules'), false)
        ->assertSee(route('news.show', $news), false);
});

test('descriptions are clipped without cutting words', function () {
    expect(Seo::clip(str_repeat('słowo ', 60)))->toEndWith('…')
        ->and(mb_strlen(Seo::clip(str_repeat('słowo ', 60))))->toBeLessThanOrEqual(160);
});
