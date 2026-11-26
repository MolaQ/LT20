<?php

namespace App\Models;

use App\Enums\PlayerPosition;
use App\Enums\PlayerStatus;
use App\Support\Squad;
use Illuminate\Database\Eloquent\Model;

/**
 * Zawodnik z kadry Lecha, do desygnowania przez graczy. Panel `dashboard/lech-squad`, start z `LechSquadSeeder`.
 *
 * @property int $id
 * @property string $name
 * @property int|null $number
 * @property PlayerPosition $position
 * @property bool $is_academy
 * @property PlayerStatus $status
 */
class LechPlayer extends Model
{
    protected $fillable = ['name', 'number', 'position', 'is_academy', 'status'];

    protected $attributes = ['status' => 'regular', 'is_academy' => false];

    protected static function booted(): void
    {
        // Zawodnik oznaczony jako niedostępny znika ze składów kolejek przed gwizdkiem.
        static::saved(function (LechPlayer $player) {
            if ($player->wasChanged('status') && $player->status === PlayerStatus::Excluded) {
                Squad::dropUnavailable($player->id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'position' => PlayerPosition::class,
            'is_academy' => 'boolean',
            'status' => PlayerStatus::class,
        ];
    }
}
