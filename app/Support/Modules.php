<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Moduły gry włączane i wyłączane w panelu admina (Ustawienia gry → Moduły, 28.11.2026). Wyłączony moduł znika
 * graczom (strony, linki, kafelki, powiadomienia) i nie jest liczony przy rozliczaniu kolejki, ale jego dane zostają,
 * więc po ponownym włączeniu wszystko wraca. Stan w tabeli modules (brak wiersza = DEFAULTS), w pamięci podręcznej.
 */
final class Modules
{
    /** Sparingi (App\Support\Friendlies). */
    public const FRIENDLIES = 'friendlies';

    /** Wartości domyślne: moduł => włączony. */
    public const DEFAULTS = [self::FRIENDLIES => true];

    private const CACHE = 'modules';

    public static function enabled(string $module): bool
    {
        return (bool) (self::all()[$module] ?? false);
    }

    /** @return array<string, bool> */
    public static function all(): array
    {
        try {
            /** @var array<string, bool> $stored */
            $stored = Cache::rememberForever(self::CACHE, fn () => Schema::hasTable('modules')
                ? DB::table('modules')->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all()
                : []);
        } catch (Throwable) {
            $stored = [];
        }

        return array_intersect_key($stored, self::DEFAULTS) + self::DEFAULTS;
    }

    public static function set(string $module, bool $enabled): void
    {
        if (! array_key_exists($module, self::DEFAULTS)) {
            return;
        }

        DB::table('modules')->upsert([['key' => $module, 'enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()]], ['key'], ['enabled', 'updated_at']);
        Cache::forget(self::CACHE);
    }

    /**
     * Opisy do panelu: moduł => [nazwa, opis].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function labels(): array
    {
        return [
            self::FRIENDLIES => [__('Friendlies'), __('Players challenge each other to friendlies in a free competition type. Turned off: the page, the links, the tiles on the tips page and the notifications disappear and friendlies are not scored. Results and records stay and come back when you turn it on again.')],
        ];
    }
}
