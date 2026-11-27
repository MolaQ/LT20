<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sparing: mecz towarzyski dwóch graczy w jednej kolejce (App\Support\Friendlies). Każdy gra w swoim wolnym typie
 * rozgrywek (challenger_set, opponent_set): z pytaniami i składem tego typu, liczony jak zwykły pojedynek.
 * Bez punktów do tabel i Hall of Fame, tylko bilans.
 *
 * @property int $id
 * @property int $matchday_id
 * @property int $challenger_id
 * @property int|null $opponent_id pusty: otwarte zaproszenie z linku
 * @property string $token
 * @property string $challenger_set
 * @property string|null $opponent_set
 * @property string $status pending, accepted, declined, cancelled
 * @property int|null $challenger_goals
 * @property int|null $opponent_goals
 * @property Carbon|null $settled_at
 */
class Friendly extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['matchday_id', 'challenger_id', 'opponent_id', 'token', 'challenger_set', 'opponent_set', 'status',
        'challenger_goals', 'opponent_goals', 'settled_at'];

    protected function casts(): array
    {
        return [
            'challenger_goals' => 'integer',
            'opponent_goals' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Matchday, $this> */
    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    /** @return BelongsTo<User, $this> */
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    /** @return BelongsTo<User, $this> */
    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    /**
     * Sparingi gracza (jako wyzywający albo wyzwany).
     *
     * @param  Builder<Friendly>  $query
     */
    public function scopeOf(Builder $query, int $userId): void
    {
        $query->where(fn ($q) => $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId));
    }

    /** Czy sparing blokuje miejsce w kolejce (czeka na odpowiedź albo przyjęty). */
    public function isActive(): bool
    {
        return in_array($this->status, [self::PENDING, self::ACCEPTED], true);
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /** Typ rozgrywek gracza w tym sparingu. */
    public function setFor(int $userId): ?string
    {
        return match ($userId) {
            $this->challenger_id => $this->challenger_set,
            $this->opponent_id => $this->opponent_set,
            default => null,
        };
    }

    /** Rywal gracza w tym sparingu. */
    public function rivalOf(int $userId): ?User
    {
        return $userId === $this->challenger_id ? $this->opponent : $this->challenger;
    }

    /** Wynik z punktu widzenia gracza: [jego bramki, rywala] albo null przed rozliczeniem. */
    public function scoreFor(int $userId): ?array
    {
        if (! $this->isSettled()) {
            return null;
        }

        return $userId === $this->challenger_id
            ? [(int) $this->challenger_goals, (int) $this->opponent_goals]
            : [(int) $this->opponent_goals, (int) $this->challenger_goals];
    }
}
