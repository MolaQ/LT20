<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Dzienne liczniki kliknięć w blok partnera rozgrywek. Dzień zapisany jako tekst Y-m-d.
 *
 * @property int $sponsor_id
 * @property string $day
 * @property int $clicks
 */
class SponsorClick extends Model
{
    public $timestamps = false;

    protected $fillable = ['sponsor_id', 'day', 'clicks'];

    protected function casts(): array
    {
        return ['clicks' => 'integer'];
    }

    /** Dolicza jedno kliknięcie do dzisiejszego wiersza (tworzy go przy pierwszym). */
    public static function bump(int $sponsorId): void
    {
        $day = now()->toDateString();
        $row = fn () => static::query()->where('sponsor_id', $sponsorId)->where('day', $day);

        if ($row()->increment('clicks') > 0) {
            return;
        }

        try {
            static::create(['sponsor_id' => $sponsorId, 'day' => $day, 'clicks' => 1]);
        } catch (UniqueConstraintViolationException) {
            // Równoległe żądanie utworzyło wiersz chwilę wcześniej.
            $row()->increment('clicks');
        }
    }
}
