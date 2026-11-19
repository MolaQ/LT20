<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ustawienia desygnowania zawodników Lecha (regulamin: desygnowanie, reguły R1-R10 w trakcie ustalania).
 * Wartości domyślne są tutaj, zmiany w tabeli designation_settings (panel dashboard/designation-settings).
 */
class Designation
{
    public const DEFAULTS = [
        // R1: najwyżej tylu zawodników w jednym typie rozgrywek na kolejkę.
        'max_players' => 6,
        // R7: bot wystawia w każdym typie rozgrywek losowo od min do max dostępnych zawodników.
        'bot_players_min' => 1,
        'bot_players_max' => 1,
        // R4: tyle punktów ataku rywala zabiera czyste konto bramkarza i obrońcy (Lech nie stracił gola w meczu, zawodnik zagrał).
        'clean_sheet_goalkeeper' => 1,
        'clean_sheet_defender' => 1,
        // R6: odpoczynek w godzinach od gwizdka kolejki, w której klub wystawił zawodnika, osobno dla każdego czasu gry
        // (19.11.2026, wcześniej podstawa + godziny za połowę). Klucz rest_hours_{n}: n = 2 × całe połowy + części połów,
        // czyli 1 = część 1. połowy, 2 = cała połowa, 3 = do 90 min bez całości … 8 = cały mecz z dogrywką. Ławka bez wejścia: 0.
        'rest_hours_1' => 40,
        'rest_hours_2' => 70,
        'rest_hours_3' => 100,
        'rest_hours_4' => 130,
        'rest_hours_5' => 160,
        'rest_hours_6' => 190,
        'rest_hours_7' => 220,
        'rest_hours_8' => 250,
        // Doświadczenie, premia za skład: wyjściowa jedenastka, wejście z ławki.
        'exp_start' => 1,
        'exp_sub' => 0.5,
        // Doświadczenie, premia za czas gry: cała połowa, część połowy (dogrywka to dwie kolejne połowy).
        'exp_full_half' => 1,
        'exp_partial_half' => 0.5,
        // Doświadczenie, premia za dokonania: czyste konto (bramkarz, obrońca), gol (także w serii karnych), asysta.
        'exp_clean_sheet' => 1,
        'exp_goal' => 1,
        'exp_assist' => 1,
    ];

    /** @var array<string, float>|null */
    private static ?array $values = null;

    /** Ustawienie całkowite (limity, punkty obrony, godziny odpoczynku). */
    public static function value(string $key): int
    {
        return (int) (self::values()[$key] ?? 0);
    }

    /** Ustawienie z ułamkiem (punkty doświadczenia, np. 0,5). */
    public static function number(string $key): float
    {
        return self::values()[$key] ?? 0.0;
    }

    /** @return array<string, float> */
    public static function values(): array
    {
        if (self::$values === null) {
            $saved = Schema::hasTable('designation_settings')
                ? DB::table('designation_settings')->pluck('value', 'key')->map(fn ($v) => (float) $v)->all()
                : [];

            self::$values = array_map('floatval', array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS)));
        }

        return self::$values;
    }

    /** Po zapisie ustawień w panelu. */
    public static function forget(): void
    {
        self::$values = null;
    }
}
