<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ranga wspierających (App\Support\SupporterRanks): nazwa, próg sumy wpłat z 12 miesięcy w groszach i kolor.
 *
 * @property int $id
 * @property string $name
 * @property int $threshold
 * @property string $color
 */
class SupporterRank extends Model
{
    protected $fillable = ['name', 'threshold', 'color'];

    protected function casts(): array
    {
        return ['threshold' => 'integer'];
    }
}
