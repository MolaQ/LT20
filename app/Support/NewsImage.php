<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Obrazek do treści newsa: zapis w storage/app/public/news (adres /storage/news/...). Zdjęcia szersze niż
 * MAX_WIDTH są zmniejszane (GD), żeby strona ładowała się szybko także na telefonie. Bez GD plik zostaje oryginalny.
 */
final class NewsImage
{
    public const MAX_WIDTH = 1600;

    /** Zapisuje plik i zwraca jego adres do wstawienia w treść. */
    public static function store(UploadedFile $file): string
    {
        $path = $file->store('news', 'public');
        self::shrink(Storage::disk('public')->path($path));

        return asset('storage/'.$path);
    }

    /** Zmniejsza zdjęcie do MAX_WIDTH z zachowaniem proporcji (GIF zostaje bez zmian, bo mógłby stracić animację). */
    public static function shrink(string $absolutePath): void
    {
        if (! function_exists('imagecreatefromstring') || ! is_file($absolutePath)) {
            return;
        }

        $info = @getimagesize($absolutePath);
        if (! $info || $info[0] <= self::MAX_WIDTH || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return;
        }

        $source = @imagecreatefromstring((string) file_get_contents($absolutePath));
        if (! $source) {
            return;
        }

        $height = (int) round($info[1] * self::MAX_WIDTH / $info[0]);
        $target = imagecreatetruecolor(self::MAX_WIDTH, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, self::MAX_WIDTH, $height, $info[0], $info[1]);

        match ($info[2]) {
            IMAGETYPE_PNG => imagepng($target, $absolutePath, 6),
            IMAGETYPE_WEBP => imagewebp($target, $absolutePath, 85),
            default => imagejpeg($target, $absolutePath, 85),
        };
    }
}
