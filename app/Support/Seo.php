<?php

namespace App\Support;

/**
 * Opis i obrazek strony dla wyszukiwarek i podglądu linków (X, Facebook, WhatsApp): partials/head wypisuje
 * meta description, Open Graph i kartę X (summary_large_image). Strona może ustawić własny opis i obrazek
 * (np. news: początek treści i pierwsze zdjęcie); bez tego zostaje opis gry i obrazek domyślny.
 */
final class Seo
{
    public const DEFAULT_IMAGE = 'images/og-default.jpg';

    private static ?string $description = null;

    private static ?string $image = null;

    private static bool $noindex = false;

    public static function description(string $text): void
    {
        self::$description = self::clip($text);
    }

    public static function image(?string $url): void
    {
        self::$image = $url;
    }

    /** Strona poza wyszukiwarkami (panel, ustawienia, logowanie). */
    public static function noindex(): void
    {
        self::$noindex = true;
    }

    /** @return array{description: string, image: string, noindex: bool} */
    public static function current(): array
    {
        return [
            'description' => self::$description ?? __('Tip the score of every Lech Poznań match, build your squad and play in leagues and cups with other fans. Free, a few minutes per matchday.'),
            'image' => self::$image ?? asset(self::DEFAULT_IMAGE),
            'noindex' => self::$noindex,
        ];
    }

    /** Opis do ~160 znaków, bez łamania słowa. */
    public static function clip(string $text, int $limit = 160): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > 80 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:-').'…';
    }

    /** Między żądaniami (testy, kolejka) wartości się nie przenoszą. */
    public static function reset(): void
    {
        self::$description = self::$image = null;
        self::$noindex = false;
    }
}
