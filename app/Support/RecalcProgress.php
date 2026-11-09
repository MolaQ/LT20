<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Postęp przeliczenia kolejki w tle (pasek postępu w panelu admina). Zapis w cache, bo zadanie działa
 * w innym procesie niż strona (queue:work albo cron na hostingu współdzielonym).
 *
 * Stan: percent (0-100), label (etap), status (queued | running | done | failed), message (wynik albo błąd).
 */
final class RecalcProgress
{
    private const TTL = 3600;

    /**
     * Magazyn plikowy, a nie domyślny: przeliczenie działa w transakcji bazy, więc wpisy do cache w bazie
     * byłyby widoczne dopiero po jej końcu i pasek stałby w miejscu.
     */
    private static function store(): Repository
    {
        return Cache::store(config('cache.stores.file') ? 'file' : null);
    }

    /** @return array{percent: int, label: string, status: string, message: string}|null */
    public static function get(int $matchdayId): ?array
    {
        return self::store()->get(self::key($matchdayId));
    }

    public static function set(int $matchdayId, int $percent, string $label, string $status = 'running', string $message = ''): void
    {
        self::store()->put(self::key($matchdayId), [
            'percent' => max(0, min(100, $percent)),
            'label' => $label,
            'status' => $status,
            'message' => $message,
        ], self::TTL);
    }

    public static function forget(int $matchdayId): void
    {
        self::store()->forget(self::key($matchdayId));
    }

    private static function key(int $matchdayId): string
    {
        return 'recalc-progress:'.$matchdayId;
    }
}
