<?php

namespace App\Support;

use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Tip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Statystyki w stopce strony. „Teraz na stronie” liczy aktywne sesje z ostatnich ONLINE_MINUTES minut
 * (tabela sessions, SESSION_DRIVER=database), z podziałem na zalogowanych i gości. Liczby gry (gracze, typy,
 * kolejki) są w cache na kilka minut, żeby stopka nie obciążała bazy przy każdym wejściu.
 */
final class SiteStats
{
    public const ONLINE_MINUTES = 5;

    /** @return array{online: int, members: int, guests: int}|null null, gdy sesje nie są w bazie */
    public static function online(): ?array
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        return Cache::remember('site-stats:online', 60, function () {
            try {
                $table = config('session.table', 'sessions');
                if (! Schema::hasTable($table)) {
                    return null;
                }
                $active = DB::table($table)->where('last_activity', '>=', now()->subMinutes(self::ONLINE_MINUTES)->getTimestamp());
                $members = (clone $active)->whereNotNull('user_id')->distinct()->count('user_id');
                $guests = (clone $active)->whereNull('user_id')->count();

                return ['online' => $members + $guests, 'members' => $members, 'guests' => $guests];
            } catch (Throwable) {
                return null;
            }
        });
    }

    /** @return array{players: int, tips: int, played: int, matchdays: int, season: ?string} */
    public static function game(): array
    {
        return Cache::remember('site-stats:game', 600, function () {
            $season = Season::current();

            return [
                'players' => Players::eligible()->count(),
                'tips' => Tip::count(),
                'played' => $season ? Matchday::where('season_id', $season->id)->where('status', MatchdayStatus::Played->value)->count() : 0,
                'matchdays' => Matchday::PER_SEASON,
                'season' => $season?->title,
            ];
        });
    }
}
