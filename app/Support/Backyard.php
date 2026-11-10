<?php

namespace App\Support;

use App\Enums\League;

/**
 * Struktura nowej Ligi podwórkowej (przebudowa 2.0, ustalenia z 9.11.2026). Czyste obliczenia bez bazy.
 *
 *  - Poziom 1: ligi 1.1–1.4, poziom 2: 2.1–2.16, poziom 3: 3.1–3.64 … (każdy poziom 4 razy większy), po 10 zespołów.
 *  - Ligi mają numery kolejne (division 1, 2, 3 …) w kolejności listy: 1 = 1.1, 4 = 1.4, 5 = 2.1, 20 = 2.16, 21 = 3.1.
 *    Miejsca listy: liga 1.1 = 101-110, 1.2 = 111-120 itd., więc ligi otwierają się same, gdy lista rośnie (między sezonami).
 *  - Ligi otwierają się po kolei pod każdą ligą wyżej: 2.1 pod 1.1, 2.2 pod 1.2, 2.3 pod 1.3, 2.4 pod 1.4, 2.5 pod 1.1 …
 *  - Awansuje zwycięzca (z poziomu 1 do C klasy), a spada tyle zespołów, ile otwartych lig jest pod ligą (najwyżej 4),
 *    po jednym do każdej. Z C klasy spada po jednym zespole do każdej otwartej ligi 1.x.
 */
final class Backyard
{
    /** Ligi pod jedną ligą wyżej (i liczba lig poziomu 1). */
    public const BRANCH = 4;

    /** Awansuje zwycięzca ligi. */
    public const PROMOTED = 1;

    /** Liczba lig na poziomie (1 => 4, 2 => 16 …). */
    public static function levelSize(int $level): int
    {
        return self::BRANCH ** $level;
    }

    /** Liczba lig na poziomach powyżej danego (numer ostatniej ligi poziomu wyżej). */
    public static function offset(int $level): int
    {
        return intdiv(self::BRANCH ** $level - self::BRANCH, self::BRANCH - 1);
    }

    public static function level(int $division): int
    {
        $level = 1;
        while ($division > self::offset($level + 1)) {
            $level++;
        }

        return $level;
    }

    /** Numer ligi na jej poziomie (2.7 => 7). */
    public static function index(int $division): int
    {
        return $division - self::offset(self::level($division));
    }

    /** Etykieta ligi, np. „2.7”. */
    public static function label(int $division): string
    {
        return self::level($division).'.'.self::index($division);
    }

    /** Pełna nazwa, np. „Liga podwórkowa 2.7”. */
    public static function name(int $division): string
    {
        return League::Podworkowa->label().' '.self::label($division);
    }

    /** Liga wyżej albo null dla poziomu 1 (nad nim jest C klasa). */
    public static function parent(int $division): ?int
    {
        $level = self::level($division);

        if ($level === 1) {
            return null;
        }

        return self::offset($level - 1) + (self::index($division) - 1) % self::levelSize($level - 1) + 1;
    }

    /**
     * Otwarte ligi pod daną ligą (0 = pod C klasą), w kolejności listy.
     *
     * @return list<int>
     */
    public static function children(int $division, int $open): array
    {
        if ($division === 0) {
            return array_values(array_filter(range(1, self::BRANCH), fn (int $d) => $d <= $open));
        }

        $level = self::level($division);
        $base = self::offset($level + 1) + self::index($division);
        $children = [];

        for ($k = 0; $k < self::BRANCH; $k++) {
            $child = $base + $k * self::levelSize($level);
            if ($child <= $open) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /** Liczba miejsc spadkowych ligi przy $open otwartych ligach podwórkowych (0 = pod C klasą). */
    public static function relegations(int $division, int $open): int
    {
        return count(self::children($division, $open));
    }

    /** Liczba lig podwórkowych dla listy z $teams zespołami (ostatnia może być niepełna). */
    public static function count(int $teams): int
    {
        return (int) ceil(max(0, $teams - League::TOP_TEAMS) / League::SIZE);
    }

    /** Pierwsze miejsce listy należące do ligi (1.1 => 101). */
    public static function firstPosition(int $division): int
    {
        return League::TOP_TEAMS + ($division - 1) * League::SIZE + 1;
    }

    /** Liga podwórkowa dla miejsca na liście albo null dla lig 1-10. */
    public static function forPosition(int $position): ?int
    {
        return $position > League::TOP_TEAMS ? intdiv($position - League::TOP_TEAMS - 1, League::SIZE) + 1 : null;
    }
}
