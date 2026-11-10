<?php

namespace App\Support;

use App\Models\PageView;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Licznik odwiedzin w stopce (wzór z jokerpila.pl), z odsłon zapisanych przez TrackPageView:
 * osoby na stronie w ostatnich ONLINE_MINUTES minutach, goście dziś, wszystkie wizyty (odwiedzający raz dziennie)
 * i wszystkie odsłony. Liczby w cache na kilka sekund, wizyty rzadziej (cięższe zapytanie).
 */
final class SiteStats
{
    public const ONLINE_MINUTES = 5;

    /** @return array{online: int, today: int, visits: int, total: int} */
    public static function visits(): array
    {
        try {
            $counts = Cache::remember('site-stats:visits', 10, fn () => [
                'online' => PageView::where('created_at', '>=', now()->subMinutes(self::ONLINE_MINUTES))->distinct()->count('visitor_hash'),
                'today' => PageView::where('created_at', '>=', today())->distinct()->count('visitor_hash'),
                'total' => PageView::count(),
            ]);
            $visits = Cache::remember('site-stats:all-visits', 120, fn () => PageView::distinct()->count('visitor_hash'));
        } catch (Throwable) {
            return ['online' => 1, 'today' => 1, 'visits' => 1, 'total' => 0];
        }

        // Oglądający sam jest na stronie, choć jego wejście mogło się jeszcze nie zapisać.
        return [
            'online' => max(1, (int) $counts['online']),
            'today' => max(1, (int) $counts['today']),
            'visits' => max(1, (int) $visits),
            'total' => (int) $counts['total'],
        ];
    }
}
