<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Kolory strony z panelu (Kolory strony): barwy klubu, belki i banery, tło i tekst jasnego motywu, stany
 * (dobrze/źle) i tablica odliczania. Każdy kolor ma wartość domyślną; zmienione są w tabeli site_colors.
 * partials/club-theme wypisuje je jako zmienne CSS, więc zmiana działa od razu na całej stronie i w panelu.
 */
final class SiteColors
{
    /** klucz => [zmienna CSS, wartość domyślna, grupa] (barwy klubu domyślnie z config/club.php). */
    public const DEFAULTS = [
        'primary' => ['--club-primary', null, 'club'],
        'primary_dark' => ['--club-primary-dark', null, 'club'],
        'deep' => ['--club-deep', null, 'club'],
        'accent' => ['--club-accent', null, 'club'],
        'bar_from' => ['--theme-bar-from', '#1740b0', 'bars'],
        'bar_to' => ['--theme-bar-to', '#12348d', 'bars'],
        'bar_ink' => ['--theme-bar-ink', '#ffffff', 'bars'],
        'bg' => ['--ui-bg', '#f7f9fd', 'light'],
        'surface' => ['--ui-surface', '#ffffff', 'light'],
        'raised' => ['--ui-raised', '#eaf0fa', 'light'],
        'ink' => ['--ui-ink', '#0d1a33', 'light'],
        'muted' => ['--ui-muted', '#5a6886', 'light'],
        'line' => ['--ui-line', '#d6dfee', 'light'],
        'good' => ['--ui-good', '#1a7f45', 'states'],
        'bad' => ['--ui-bad', '#c2410c', 'states'],
        'flap_bg' => ['--flap-bg', '#1d1f24', 'clock'],
        'flap_ink' => ['--flap-ink', '#f4f1e8', 'clock'],
    ];

    /** Klucze z config/club.php (barwy klubu). */
    private const CLUB = ['primary' => 'primary', 'primary_dark' => 'primary_dark', 'deep' => 'deep', 'accent' => 'accent'];

    /** Grupy jasnego motywu: te zmienne ciemny motyw ma własne, więc idą tylko do :root:not(.dark). */
    public const LIGHT_ONLY = ['light', 'states'];

    /** @return array<string, string> grupa => nazwa */
    public static function groups(): array
    {
        return [
            'club' => __('Club colors'),
            'bars' => __('Bars and banners'),
            'light' => __('Light theme'),
            'states' => __('Good and bad'),
            'clock' => __('Countdown board'),
        ];
    }

    /** @return array<string, string> klucz => nazwa koloru */
    public static function labels(): array
    {
        return [
            'primary' => __('Club color'),
            'primary_dark' => __('Club color in the dark theme'),
            'deep' => __('Dark theme background'),
            'accent' => __('Accent (points, premium)'),
            'bar_from' => __('Bar gradient start'),
            'bar_to' => __('Bar gradient end'),
            'bar_ink' => __('Text on bars'),
            'bg' => __('Page background'),
            'surface' => __('Cards'),
            'raised' => __('Raised elements'),
            'ink' => __('Text'),
            'muted' => __('Secondary text'),
            'line' => __('Lines and borders'),
            'good' => __('Good (wins, open)'),
            'bad' => __('Bad (losses, errors)'),
            'flap_bg' => __('Flap'),
            'flap_ink' => __('Digits'),
        ];
    }

    /** @return array<string, string> klucz => domyślny kolor */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => [, $default]) {
            $out[$key] = strtolower($default ?? (string) config('club.colors.'.self::CLUB[$key]));
        }

        return $out;
    }

    /** @return array<string, string> klucz => obowiązujący kolor (domyślny albo z panelu) */
    public static function all(): array
    {
        return array_replace(self::defaults(), self::saved());
    }

    /** @return array<string, string> tylko kolory zmienione w panelu */
    public static function saved(): array
    {
        // Bez bazy (np. strona błędu, cache w bazie) zostają kolory domyślne.
        try {
            return Cache::rememberForever('site-colors', function () {
                if (! Schema::hasTable('site_colors')) {
                    return [];
                }

                return DB::table('site_colors')->pluck('value', 'key')
                    ->filter(fn ($v, $k) => isset(self::DEFAULTS[$k]) && self::isColor($v))->all();
            });
        } catch (Throwable) {
            return [];
        }
    }

    /** Zapis z panelu: kolory równe domyślnym nie są przechowywane. */
    public static function save(array $colors): void
    {
        $defaults = self::defaults();
        $now = now();
        $rows = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $value = strtolower((string) ($colors[$key] ?? ''));
            if (self::isColor($value) && $value !== $defaults[$key]) {
                $rows[] = ['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::transaction(function () use ($rows) {
            DB::table('site_colors')->delete();
            if ($rows !== []) {
                DB::table('site_colors')->insert($rows);
            }
        });
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget('site-colors');
    }

    public static function isColor(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value);
    }

    /**
     * Zmienne CSS do <style>: [':root' => 'zmienna: kolor; ...', ':root:not(.dark)' => ...].
     *
     * @param  array<string, string>|null  $colors  podgląd z panelu; domyślnie obowiązujące
     * @return array<string, string>
     */
    public static function css(?array $colors = null): array
    {
        $colors ??= self::all();
        $root = $light = '';
        foreach (self::DEFAULTS as $key => [$var, , $group]) {
            $value = self::isColor($colors[$key] ?? null) ? $colors[$key] : self::defaults()[$key];
            if (in_array($group, self::LIGHT_ONLY, true)) {
                $light .= "$var: $value; ";
            } else {
                $root .= "$var: $value; ";
            }
        }

        return [':root' => trim($root), ':root:not(.dark)' => trim($light)];
    }
}
