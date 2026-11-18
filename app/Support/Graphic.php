<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Proste płótno GD do obrazków podglądu linków i grafik promocyjnych (kampania, 18.11.2026): gradienty, poświata,
 * ukośne pasy, zaokrąglone prostokąty, napisy czcionką Inter (resources/fonts) i logo. Rysujemy w podwójnej
 * rozdzielczości i na końcu zmniejszamy, dzięki czemu krawędzie są gładkie. Wymaga GD z FreeType (available()).
 */
final class Graphic
{
    private const SCALE = 2;

    private GdImage $image;

    public function __construct(public readonly int $width, public readonly int $height)
    {
        $image = imagecreatetruecolor(max(1, $width * self::SCALE), max(1, $height * self::SCALE));
        if ($image === false) {
            throw new RuntimeException('Cannot create the image.');
        }
        $this->image = $image;
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);
    }

    /** Czy serwer potrafi rysować napisy (GD z FreeType). */
    public static function available(): bool
    {
        return function_exists('imagettftext') && function_exists('imagepng');
    }

    /** Kolor z #rrggbb i krycia 0–1. */
    public function color(string $hex, float $opacity = 1.0): int
    {
        [$r, $g, $b] = self::rgb($hex);

        return (int) imagecolorallocatealpha($this->image, $r, $g, $b, max(0, min(127, (int) round(127 * (1 - $opacity)))));
    }

    /** Ukośny gradient przez całe tło (lewy górny róg → prawy dolny). */
    public function gradient(string $from, string $to): self
    {
        [$r1, $g1, $b1] = self::rgb($from);
        [$r2, $g2, $b2] = self::rgb($to);
        $w = $this->width * self::SCALE;
        $h = $this->height * self::SCALE;
        $steps = $w + $h;
        for ($t = 0; $t <= $steps; $t += 2) {
            $k = $t / $steps;
            $c = (int) imagecolorallocate($this->image, self::channel($r1 + ($r2 - $r1) * $k), self::channel($g1 + ($g2 - $g1) * $k), self::channel($b1 + ($b2 - $b1) * $k));
            imagesetthickness($this->image, 3);
            imageline($this->image, $t, 0, 0, $t, $c);
        }
        imagesetthickness($this->image, 1);

        return $this;
    }

    /** Miękka poświata (koncentryczne koła o rosnącej przezroczystości). */
    public function glow(int $cx, int $cy, int $radius, string $hex, float $opacity = 0.4): self
    {
        $rings = 24;
        for ($i = $rings; $i >= 1; $i--) {
            $r = $radius * $i / $rings;
            $this->ellipse($cx, $cy, (int) $r, (int) $r, $hex, $opacity / $rings * 1.6);
        }

        return $this;
    }

    /** Ukośne półprzezroczyste pasy od $x w prawo (jak na grafikach kampanii). */
    public function stripes(int $x, int $band = 70, float $opacity = 0.06): self
    {
        $color = $this->color('#ffffff', $opacity);
        $slant = (int) ($this->height * 0.32);
        for ($left = $x; $left < $this->width + $slant; $left += $band * 2) {
            $points = [$left + $slant, 0, $left + $slant + $band, 0, $left + $band, $this->height, $left, $this->height];
            imagefilledpolygon($this->image, array_map(fn ($p) => $p * self::SCALE, $points), $color);
        }

        return $this;
    }

    public function ellipse(int $cx, int $cy, int $rx, int $ry, string $hex, float $opacity = 1.0): self
    {
        imagefilledellipse($this->image, $cx * self::SCALE, $cy * self::SCALE, $rx * 2 * self::SCALE, $ry * 2 * self::SCALE, $this->color($hex, $opacity));

        return $this;
    }

    /** Wypełniony prostokąt z zaokrąglonymi rogami (półprzezroczysty też, bez nakładania się rogów). */
    public function rect(int $x, int $y, int $w, int $h, int $radius, string $hex, float $opacity = 1.0): self
    {
        $s = self::SCALE;
        [$x, $y, $w, $h, $r] = [$x * $s, $y * $s, $w * $s, $h * $s, min($radius, intdiv(min($w, $h), 2)) * $s];
        $color = $this->color($hex, $opacity);

        if ($r <= 0) {
            imagefilledrectangle($this->image, $x, $y, $x + $w - 1, $y + $h - 1, $color);

            return $this;
        }

        imagefilledrectangle($this->image, $x + $r, $y, $x + $w - $r - 1, $y + $h - 1, $color);
        imagefilledrectangle($this->image, $x, $y + $r, $x + $r - 1, $y + $h - $r - 1, $color);
        imagefilledrectangle($this->image, $x + $w - $r, $y + $r, $x + $w - 1, $y + $h - $r - 1, $color);
        foreach ([[$x + $r, $y + $r, 180], [$x + $w - $r - 1, $y + $r, 270], [$x + $w - $r - 1, $y + $h - $r - 1, 0], [$x + $r, $y + $h - $r - 1, 90]] as [$cx, $cy, $start]) {
            imagefilledarc($this->image, $cx, $cy, $r * 2, $r * 2, $start, $start + 90, $color, IMG_ARC_PIE);
        }

        return $this;
    }

    /** Szerokość napisu w pikselach. */
    public function measure(string $text, int $size, string $weight = 'Bold'): int
    {
        $box = imagettfbbox($this->points($size), 0, self::font($weight), $text);

        return $box === false ? 0 : (int) ceil(abs($box[2] - $box[0]) / self::SCALE);
    }

    /**
     * Napis: $y to linia bazowa, $align left|center|right względem $x. Zbyt długi napis skraca się wielokropkiem
     * do $maxWidth. Zwraca szerokość narysowanego napisu.
     */
    public function text(int $x, int $y, string $text, int $size, string $hex, string $weight = 'Bold', string $align = 'left', ?int $maxWidth = null, float $opacity = 1.0): int
    {
        $text = $maxWidth ? $this->fit($text, $size, $weight, $maxWidth) : $text;
        $width = $this->measure($text, $size, $weight);
        $left = match ($align) {
            'center' => $x - intdiv($width, 2),
            'right' => $x - $width,
            default => $x,
        };
        imagettftext($this->image, $this->points($size), 0, $left * self::SCALE, $y * self::SCALE, $this->color($hex, $opacity), self::font($weight), $text);

        return $width;
    }

    /** Napis przycięty do szerokości (z wielokropkiem). */
    public function fit(string $text, int $size, string $weight, int $maxWidth): string
    {
        if ($this->measure($text, $size, $weight) <= $maxWidth) {
            return $text;
        }
        while (mb_strlen($text) > 1 && $this->measure($text.'…', $size, $weight) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    /** Największy rozmiar (od $size w dół), przy którym napis mieści się w $maxWidth. */
    public function fitSize(string $text, int $size, string $weight, int $maxWidth, int $min = 12): int
    {
        while ($size > $min && $this->measure($text, $size, $weight) > $maxWidth) {
            $size -= 2;
        }

        return $size;
    }

    /** Logo gry w białym kole (public/images/lechtyper-logo.png). */
    public function logo(int $x, int $y, int $size): self
    {
        $this->ellipse($x + intdiv($size, 2), $y + intdiv($size, 2), intdiv($size, 2), intdiv($size, 2), '#ffffff');
        $path = public_path('images/lechtyper-logo.png');
        $logo = is_file($path) ? @imagecreatefrompng($path) : false;
        if ($logo !== false) {
            $inner = (int) round($size * 0.8);
            $offset = intdiv($size - $inner, 2);
            imagecopyresampled($this->image, $logo, ($x + $offset) * self::SCALE, ($y + $offset) * self::SCALE, 0, 0, $inner * self::SCALE, $inner * self::SCALE, imagesx($logo), imagesy($logo));
        }

        return $this;
    }

    /** Gotowy PNG w docelowym rozmiarze. */
    public function png(): string
    {
        $out = imagecreatetruecolor(max(1, $this->width), max(1, $this->height));
        if ($out === false) {
            throw new RuntimeException('Cannot create the image.');
        }
        imagecopyresampled($out, $this->image, 0, 0, 0, 0, $this->width, $this->height, $this->width * self::SCALE, $this->height * self::SCALE);
        ob_start();
        imagepng($out, null, 6);

        return (string) ob_get_clean();
    }

    /** Piksele na punkty GD (96 dpi) w skali rysowania. */
    private function points(int $size): float
    {
        return $size * self::SCALE * 0.75;
    }

    private static function font(string $weight): string
    {
        $path = resource_path('fonts/Inter-'.$weight.'.otf');

        return is_file($path) ? $path : resource_path('fonts/Inter-Bold.otf');
    }

    /** Składowa koloru w zakresie 0–255. @return int<0, 255> */
    private static function channel(float|int $value): int
    {
        return max(0, min(255, (int) round($value)));
    }

    /** @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return [0, 0, 0];
        }

        return [self::channel((int) hexdec(substr($hex, 0, 2))), self::channel((int) hexdec(substr($hex, 2, 2))), self::channel((int) hexdec(substr($hex, 4, 2)))];
    }
}
