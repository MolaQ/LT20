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
 * @property array<string, string>|null $squad_rules
 * @property string $squad_vs_bot
 * @property array<int, int>|null $squad_priority
 * @property array{matchday_id: int, sets: array<string, array<int, int>>}|null $squad_override
 * @property-read User $user
 */
class Automat extends Model
{
    public const MODE_POPULAR = 'popular';

    public const MODE_FIXED = 'fixed';

    public const MODES = [self::MODE_POPULAR, self::MODE_FIXED];

    /** Strategie składu: najbardziej doświadczeni, własna kolejność, losowo, nikogo. */
    public const SQUAD_STRATEGIES = ['experience', 'priority', 'random', 'none'];

    /** Strategia na mecz z botem: jak w rozgrywkach albo jedna ze strategii składu. */
    public const SQUAD_SAME = 'same';

    protected $fillable = ['user_id', 'tip_mode', 'tip_lech', 'tip_opponent', 'minutes_before', 'active', 'last_matchday_id', 'last_run_at',
        'squad_rules', 'squad_vs_bot', 'squad_priority', 'squad_override'];

    protected $attributes = ['tip_mode' => self::MODE_POPULAR, 'tip_lech' => 2, 'tip_opponent' => 1, 'minutes_before' => 60, 'active' => true, 'squad_vs_bot' => self::SQUAD_SAME];

    protected function casts(): array
    {
        return [
            'tip_lech' => 'integer',
            'tip_opponent' => 'integer',
            'minutes_before' => 'integer',
            'active' => 'boolean',
            'last_run_at' => 'datetime',
            'squad_rules' => 'array',
            'squad_priority' => 'array',
            'squad_override' => 'array',
        ];
    }

    /** @return array<string, string> strategia składu => nazwa */
    public static function strategyLabels(): array
    {
        return [
            'experience' => __('Most experienced'),
            'priority' => __('Own order'),
            'random' => __('Random'),
            'none' => __('Nobody'),
        ];
    }

    /** Strategia składu w typie rozgrywek (domyślnie najbardziej doświadczeni). */
    public function strategyFor(string $set): string
    {
        $strategy = $this->squad_rules[$set] ?? 'experience';

        return in_array($strategy, self::SQUAD_STRATEGIES, true) ? $strategy : 'experience';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
