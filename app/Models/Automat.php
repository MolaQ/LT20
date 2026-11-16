<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Automat: konto gracza, które samo gra o ustawionej porze przed meczem (App\Support\Automats).
 * Dla innych graczy to zwykły zespół; znacznik i strategię widzi tylko panel admina.
 *
 * @property int $id
 * @property int $user_id
 * @property string $tip_mode
 * @property int $tip_lech
 * @property int $tip_opponent
 * @property int $minutes_before
 * @property bool $active
 * @property int|null $last_matchday_id
 * @property Carbon|null $last_run_at
 * @property-read User $user
 */
class Automat extends Model
{
    public const MODE_POPULAR = 'popular';

    public const MODE_FIXED = 'fixed';

    public const MODES = [self::MODE_POPULAR, self::MODE_FIXED];

    protected $fillable = ['user_id', 'tip_mode', 'tip_lech', 'tip_opponent', 'minutes_before', 'active', 'last_matchday_id', 'last_run_at'];

    protected $attributes = ['tip_mode' => self::MODE_POPULAR, 'tip_lech' => 2, 'tip_opponent' => 1, 'minutes_before' => 60, 'active' => true];

    protected function casts(): array
    {
        return [
            'tip_lech' => 'integer',
            'tip_opponent' => 'integer',
            'minutes_before' => 'integer',
            'active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
