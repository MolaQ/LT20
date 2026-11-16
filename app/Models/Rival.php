<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Rywal Lecha z bazy w panelu (Sezon → Rywale): nazwa jak w kalendarzu meczów, skrót i 1–3 barwy (#rrggbb).
 * Mecz (LechMatch, Matchday) zna rywala tylko po nazwie, więc barwy szukamy po nazwie bez względu na wielkość liter.
 *
 * @property string $name
 * @property string|null $short_name
 * @property list<string>|null $colors
 */
class Rival extends Model
{
    /** Barwy Lecha (pasek po stronie Lecha na tablicy meczu). */
    public const LECH_COLORS = ['#1d4ed8', '#ffffff'];

    protected $fillable = ['name', 'short_name', 'colors'];

    protected function casts(): array
    {
        return ['colors' => 'array'];
    }

    /** @var array<string, list<string>>|null nazwa małymi literami => barwy (cache na żądanie) */
    private static ?array $byName = null;

    /**
     * Barwy rywala o tej nazwie albo pusta lista, gdy nie ma go w bazie.
     *
     * @return list<string>
     */
    public static function colorsFor(?string $name): array
    {
        if (blank($name)) {
            return [];
        }

        if (self::$byName === null) {
            try {
                self::$byName = self::all(['name', 'colors'])
                    ->mapWithKeys(fn (self $rival) => [mb_strtolower($rival->name) => array_values($rival->colors ?? [])])
                    ->all();
            } catch (QueryException) {
                self::$byName = []; // tabela jeszcze bez migracji
            }
        }

        return self::$byName[mb_strtolower(trim($name))] ?? [];
    }

    /**
     * Pasek barw jako tło CSS (równe pasy), np. dla tablicy meczu.
     *
     * @param  array<int, string>  $colors
     */
    public static function stripe(array $colors): string
    {
        $count = count($colors);
        if ($count === 0) {
            return '';
        }

        $parts = [];
        foreach (array_values($colors) as $i => $color) {
            $parts[] = $color.' '.round($i * 100 / $count, 2).'% '.round(($i + 1) * 100 / $count, 2).'%';
        }

        return 'background: linear-gradient(90deg, '.implode(', ', $parts).')';
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$byName = null);
        static::deleted(fn () => self::$byName = null);
    }
}
