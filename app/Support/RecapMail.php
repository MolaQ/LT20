<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Enums\MatchdayStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Friendly;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamScore;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\MatchdayRecap;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Mail z podsumowaniem kolejki (28.11.2026), rano po meczu (SEND_HOUR dnia po pierwszym gwizdku): wynik Lecha,
 * typ i punkty gracza, jego mecz ligowy, miejsce w tabeli, sparing i następna kolejka z przyciskiem do typowania.
 * Do graczy z listy sezonu z potwierdzonym adresem i prawem gry, którzy nie wyłączyli maila (users.email_recap).
 * Raz na kolejkę (matchdays.recap_email_at), z harmonogramu co 5 minut; kolejki starsze niż MAX_DAYS dni po meczu
 * już nie (np. pierwsze uruchomienie po wdrożeniu nie wysyła zaległych podsumowań).
 */
final class RecapMail
{
    public const SEND_HOUR = 9;

    public const MAX_DAYS = 3;

    public static function tick(): int
    {
        $season = Season::current();
        if (! $season) {
            return 0;
        }

        $matchday = Matchday::where('season_id', $season->id)
            ->where('status', MatchdayStatus::Played->value)
            ->whereNull('recap_email_at')
            ->whereNotNull('lech_goals')
            ->whereNotNull('kickoff_at')
            ->where('kickoff_at', '>', now()->subDays(self::MAX_DAYS))
            ->orderByDesc('kickoff_at')
            ->first();

        if (! $matchday || ! $matchday->kickoff_at || now()->lt(self::sendAt($matchday))) {
            return 0;
        }

        $matchday->forceFill(['recap_email_at' => now()])->save();

        return self::send($matchday);
    }

    /** Pora wysyłki: rano dnia po meczu. */
    public static function sendAt(Matchday $matchday): CarbonInterface
    {
        return $matchday->kickoff_at->copy()->addDay()->setTime(self::SEND_HOUR, 0);
    }

    /** @return Collection<int, User> */
    public static function recipients(Matchday $matchday): Collection
    {
        return User::with('roles')
            ->whereIn('id', SeasonTeam::where('season_id', $matchday->season_id)->whereNotNull('user_id')->select('user_id'))
            ->whereNotNull('email_verified_at')
            ->where('email_recap', true)
            ->get()
            ->filter(fn (User $user) => Players::canPlay($user))
            ->values();
    }

    public static function send(Matchday $matchday): int
    {
        $users = self::recipients($matchday);
        $next = Matchday::where('season_id', $matchday->season_id)
            ->where('status', MatchdayStatus::Planned->value)
            ->where('is_draft', false)
            ->whereNotNull('kickoff_at')
            ->where('kickoff_at', '>', now())
            ->orderBy('kickoff_at')
            ->first();
        /** @var array<int, array<int, int>> $ranks */
        $ranks = [];

        foreach ($users as $user) {
            try {
                $recap = self::for($user, $matchday, $ranks);
                $user->notify((new MatchdayRecap($matchday, $recap, $next))->locale($user->locale ?: config('app.locale')));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $users->count();
    }

    /**
     * Dorobek gracza w kolejce. $ranks to pamięć tabel (id rozgrywek => id wpisów od najlepszego), wspólna dla całej wysyłki.
     *
     * @param  array<int, array<int, int>>  $ranks
     *
     * @param-out array<int, array<int, int>>  $ranks
     *
     * @return array{tip: array{0: int, 1: int}|null, points: int, bonus: int, duel: array{mine: int, theirs: int, rival: string}|null, place: int|null, teams: int, league: string|null, friendly: array{mine: int, theirs: int, rival: string}|null}
     */
    public static function for(User $user, Matchday $matchday, array &$ranks = []): array
    {
        $out = ['tip' => null, 'points' => 0, 'bonus' => 0, 'duel' => null, 'place' => null, 'teams' => 0, 'league' => null, 'friendly' => null];

        $tip = Tip::where('matchday_id', $matchday->id)->where('user_id', $user->id)->first();
        if ($tip) {
            $out['tip'] = [(int) $tip->lech_goals, (int) $tip->opponent_goals];
        }

        $team = SeasonTeam::where('season_id', $matchday->season_id)->where('user_id', $user->id)->first();
        $entry = $team ? CompetitionEntry::with('competition')->where('season_team_id', $team->id)
            ->whereHas('competition', fn ($q) => $q->whereIn('type', [CompetitionType::League->value, CompetitionType::Swiss->value]))
            ->first() : null;

        $competition = $entry?->competition;
        if ($entry && $competition instanceof Competition) {
            $score = TeamScore::where('matchday_id', $matchday->id)->where('season_team_id', $team->id)
                ->where('score_set', CompetitionType::League->value)->first();
            if ($score) {
                $out['points'] = (int) $score->tip_points;
                $out['bonus'] = (int) $score->offense_bonus + (int) $score->defense_bonus;
            }

            $fixture = Fixture::where('competition_id', $competition->id)->where('round', $matchday->number)
                ->where(fn ($q) => $q->where('home_entry_id', $entry->id)->orWhere('away_entry_id', $entry->id))
                ->whereNotNull('home_goals')
                ->first();
            if ($fixture) {
                $home = $fixture->home_entry_id === $entry->id;
                $rival = CompetitionEntry::with('seasonTeam.user:id,name,team_name', 'seasonTeam.bot:id,name')
                    ->find($home ? $fixture->away_entry_id : $fixture->home_entry_id)?->seasonTeam;
                $rival = $rival instanceof SeasonTeam ? $rival : null;
                $out['duel'] = [
                    'mine' => (int) ($home ? $fixture->home_goals : $fixture->away_goals),
                    'theirs' => (int) ($home ? $fixture->away_goals : $fixture->home_goals),
                    'rival' => $rival ? (string) $rival->name : '',
                ];
            }

            $ranked = $ranks[$competition->id] ??= Standings::rankedEntryIds($competition);
            $index = array_search($entry->id, $ranked, true);
            $out['place'] = $index === false ? null : $index + 1;
            $out['teams'] = count($ranked);
            $out['league'] = $competition->name ?: $competition->type->label();
        }

        $friendly = Friendlies::on() ? Friendly::of($user->id)->with(['challenger', 'opponent'])->where('matchday_id', $matchday->id)->whereNotNull('settled_at')->first() : null;
        if ($friendly && ($score = $friendly->scoreFor($user->id))) {
            $out['friendly'] = ['mine' => $score[0], 'theirs' => $score[1], 'rival' => Friendlies::teamName($friendly->rivalOf($user->id))];
        }

        return $out;
    }

    /** Wynik meczu Lecha („Lech Poznań 2:1 Legia”). */
    public static function score(Matchday $matchday): string
    {
        return $matchday->is_home
            ? 'Lech Poznań '.$matchday->lech_goals.':'.$matchday->opponent_goals.' '.$matchday->opponent
            : $matchday->opponent.' '.$matchday->opponent_goals.':'.$matchday->lech_goals.' Lech Poznań';
    }
}
