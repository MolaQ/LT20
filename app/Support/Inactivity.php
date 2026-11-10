<?php

namespace App\Support;

use App\Actions\Seasons\FinishSeason;
use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\TeamScore;
use App\Models\Tip;
use App\Models\User;

/**
 * Bieżąca seria kolejek bez typu (ostrzeżenie „kartki” na stronie głównej i w typowaniu, powiadomienie push):
 * rozegrane kolejki, w których gracz miał mecz, od najnowszej wstecz, także z poprzednich sezonów, aż do
 * pierwszej z typem albo do przywrócenia ze statusu Inactive (users.inactivity_reset_at). Przy LIMIT
 * (FinishSeason::INACTIVE_STREAK) kolejkach zakończenie sezonu zmienia gracza w nieaktywnego, chyba że ma premium.
 */
final class Inactivity
{
    public const LIMIT = FinishSeason::INACTIVE_STREAK;

    public static function streak(User $user): int
    {
        // Wystarczy kilka ostatnich kolejek więcej niż próg (część mogła być bez meczu gracza).
        $matchdays = Matchday::query()
            ->join('seasons', 'seasons.id', '=', 'matchdays.season_id')
            ->where('matchdays.status', MatchdayStatus::Played->value)
            ->when($user->inactivity_reset_at, fn ($q, $reset) => $q->where('matchdays.kickoff_at', '>', $reset))
            ->orderByDesc('seasons.number')->orderByDesc('matchdays.number')
            ->limit(self::LIMIT * 3)
            ->pluck('matchdays.id');

        $played = TeamScore::whereIn('matchday_id', $matchdays)->where('user_id', $user->id)->pluck('matchday_id')->flip();
        $tipped = Tip::whereIn('matchday_id', $matchdays)->where('user_id', $user->id)->pluck('matchday_id')->flip();

        $streak = 0;
        foreach ($matchdays as $matchdayId) {
            if (! isset($played[$matchdayId])) {
                continue;
            }
            if (isset($tipped[$matchdayId])) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * Stan do ostrzeżenia: null (brak ostrzeżenia), 'warning' (żółte kartki), 'last' (ostatnia szansa)
     * albo 'protected' (premium chroni zespół).
     */
    public static function level(?User $user): ?string
    {
        if (! $user || ! Players::canPlay($user)) {
            return null;
        }

        $streak = self::streak($user);

        return match (true) {
            $streak === 0 => null,
            Premium::isActive($user) => 'protected',
            $streak >= self::LIMIT - 1 => 'last',
            default => 'warning',
        };
    }
}
