<?php

namespace App\Models;

use App\Support\Designation;
use App\Support\Questions;
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
 * @property array<string, array{strategy?: string, count?: int, bot?: string}>|null $squad_rules
 * @property array<int, string>|null $squad_order
 * @property array<int, int>|null $squad_priority
 * @property array{matchday_id: int, sets: array<string, array<int, int>>}|null $squad_override
 * @property-read User $user
 */
class Automat extends Model
{
    public const MODE_POPULAR = 'popular';

    public const MODE_FIXED = 'fixed';

    public const MODES = [self::MODE_POPULAR, self::MODE_FIXED];

    /** Strategie składu: najbardziej doświadczeni, najmniej doświadczeni, własna kolejność, losowo, nikogo. */
    public const SQUAD_STRATEGIES = ['experience', 'least', 'form', 'form_low', 'priority', 'random', 'none'];

    /** Strategia na mecz z botem: jak w rozgrywkach albo jedna ze strategii składu. */
    public const SQUAD_SAME = 'same';

    protected $fillable = ['user_id', 'tip_mode', 'tip_lech', 'tip_opponent', 'minutes_before', 'active', 'last_matchday_id', 'last_run_at',
        'squad_rules', 'squad_order', 'squad_priority', 'squad_override'];

    protected $attributes = ['tip_mode' => self::MODE_POPULAR, 'tip_lech' => 2, 'tip_opponent' => 1, 'minutes_before' => 60, 'active' => true];

    protected function casts(): array
    {
        return [
            'tip_lech' => 'integer',
            'tip_opponent' => 'integer',
            'minutes_before' => 'integer',
            'active' => 'boolean',
            'last_run_at' => 'datetime',
            'squad_rules' => 'array',
            'squad_order' => 'array',
            'squad_priority' => 'array',
            'squad_override' => 'array',
        ];
    }

    /** @return array<string, string> strategia składu => nazwa */
    public static function strategyLabels(): array
    {
        return [
            'experience' => __('Most experienced'),
            'least' => __('Least experienced'),
            'form' => __('Best form'),
            'form_low' => __('Worst form'),
            'priority' => __('Own order'),
            'random' => __('Random'),
            'none' => __('Nobody'),
        ];
    }

    /**
     * Ustawienia składu w typie rozgrywek: strategia (domyślnie najbardziej doświadczeni), liczba zawodników
     * (domyślnie najwięcej, ile wolno) i strategia na mecz z botem (domyślnie jak w rozgrywkach).
     *
     * @return array{strategy: string, count: int, bot: string}
     */
    public function rule(string $set): array
    {
        $rule = $this->squad_rules[$set] ?? [];
        $max = Designation::value('max_players');
        $strategy = in_array($rule['strategy'] ?? null, self::SQUAD_STRATEGIES, true) ? $rule['strategy'] : 'experience';
        $bot = in_array($rule['bot'] ?? null, [self::SQUAD_SAME, ...self::SQUAD_STRATEGIES], true) ? $rule['bot'] : self::SQUAD_SAME;

        return ['strategy' => $strategy, 'count' => max(0, min($max, (int) ($rule['count'] ?? $max))), 'bot' => $bot];
    }

    /**
     * Kolejność typów rozgrywek przy wyborze zawodników: zapisana, a brakujące na końcu w zwykłej kolejności.
     *
     * @return array<int, string>
     */
    public function setOrder(): array
    {
        $sets = Questions::sets();

        return array_values(array_unique(array_merge(array_intersect($this->squad_order ?? [], $sets), $sets)));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
