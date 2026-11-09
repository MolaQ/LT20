<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Enums\MatchdayStatus;
use App\Models\BotTip;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamScore;
use App\Models\Tip;
use App\Models\User;

/**
 * Statystyki premium (przebudowa 2.0, etap I): forma, punkty w kolejkach, serie i odznaki, „co jeśli” i mapa typów.
 * Obliczenia na tablicach są czyste (testy bez bazy), a metody z bazy tylko zbierają dane.
 *
 * Wiersz kolejki: number, has_tip, points (punkty za typ 0-3), bonus (atak + obrona razem z pytaniami),
 * questions (bonus z pytań), outcome, diff, exact.
 */
final class PremiumStats
{
    /** Największa liczba goli w siatkach „co jeśli” i mapy typów (wyżej = „4+”). */
    public const GRID = 4;

    /**
     * Rozliczone kolejki gracza w sezonie (tylko te, które grał sam, nie bot za niego).
     *
     * @return list<array{number: int, has_tip: bool, points: int, bonus: int, questions: int, outcome: bool, diff: bool, exact: bool}>
     */
    public static function rows(User $user, Season $season): array
    {
        $team = SeasonTeam::where('season_id', $season->id)->where('user_id', $user->id)->first();

        if (! $team) {
            return [];
        }

        return TeamScore::query()
            ->join('matchdays', 'matchdays.id', '=', 'team_scores.matchday_id')
            ->where('team_scores.season_team_id', $team->id)
            ->where('team_scores.user_id', $user->id)
            ->where('matchdays.status', MatchdayStatus::Played->value)
            ->orderBy('matchdays.number')
            ->select('team_scores.*', 'matchdays.number')
            ->get()
            ->unique('matchday_id')
            ->map(fn (TeamScore $s) => [
                'number' => (int) $s->getAttribute('number'),
                'has_tip' => (bool) $s->has_tip,
                'points' => (int) $s->tip_points,
                'bonus' => (int) $s->offense_bonus + (int) $s->defense_bonus,
                'questions' => (int) $s->question_offense + (int) $s->question_defense,
                'outcome' => (bool) $s->outcome_hit,
                'diff' => (bool) $s->diff_hit,
                'exact' => (bool) $s->exact_hit,
            ])
            ->values()
            ->all();
    }

    /**
     * Forma z ostatnich kolejek: procent zdobytych punktów za typ (3 na kolejkę).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function form(array $rows, int $last = 5): int
    {
        $recent = array_slice($rows, -$last);

        return $recent === [] ? 0 : (int) round(array_sum(array_column($recent, 'points')) / (3 * count($recent)) * 100);
    }

    /**
     * Serie trafionych rozstrzygnięć: obecna i najlepsza, oraz liczba dokładnych wyników.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{current: int, best: int, exact: int, outcome: int, played: int}
     */
    public static function streaks(array $rows): array
    {
        $current = $best = 0;
        foreach ($rows as $row) {
            $current = $row['has_tip'] && $row['outcome'] ? $current + 1 : 0;
            $best = max($best, $current);
        }

        return [
            'current' => $current,
            'best' => $best,
            'exact' => count(array_filter($rows, fn ($r) => $r['exact'])),
            'outcome' => count(array_filter($rows, fn ($r) => $r['outcome'])),
            'played' => count($rows),
        ];
    }

    /**
     * Odznaki sezonu: zdobyte i do zdobycia (z podpowiedzią).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, label: string, hint: string, earned: bool}>
     */
    public static function badges(array $rows): array
    {
        $streaks = self::streaks($rows);
        $tipped = array_filter($rows, fn ($r) => $r['has_tip']);

        $list = [
            ['sniper', __('Sniper'), __('Hit the exact score'), $streaks['exact'] > 0],
            ['streak_3', __('On a roll'), __('3 correct outcomes in a row'), $streaks['best'] >= 3],
            ['streak_5', __('Unstoppable'), __('5 correct outcomes in a row'), $streaks['best'] >= 5],
            ['ever_present', __('Ever present'), __('Tip every matchday (at least 3)'), count($rows) >= 3 && count($tipped) === count($rows)],
            ['quiz', __('Quiz master'), __('Get 3 question bonuses in one matchday'), max([0, ...array_column($rows, 'questions')]) >= 3],
            ['playmaker', __('Playmaker'), __('Get 4 bonus points in one matchday'), max([0, ...array_column($rows, 'bonus')]) >= 4],
            ['double_exact', __('Double strike'), __('Hit the exact score twice'), $streaks['exact'] >= 2],
        ];

        return array_map(fn ($b) => ['key' => $b[0], 'label' => $b[1], 'hint' => $b[2], 'earned' => $b[3]], $list);
    }

    /**
     * „Co jeśli”: punkty za typ gracza (i rywala) dla każdego wyniku meczu 0:0 … 4:4.
     *
     * @param  array{0: int, 1: int}|null  $mine  [Lech, rywal]
     * @param  array{0: int, 1: int}|null  $rival
     * @return array<int, array<int, array{me: ?int, rival: ?int}>>
     */
    public static function whatIf(?array $mine, ?array $rival): array
    {
        $grid = [];
        for ($lech = 0; $lech <= self::GRID; $lech++) {
            for ($opponent = 0; $opponent <= self::GRID; $opponent++) {
                $grid[$lech][$opponent] = [
                    'me' => $mine ? Scoring::tip($mine[0], $mine[1], $lech, $opponent)['points'] : null,
                    'rival' => $rival ? Scoring::tip($rival[0], $rival[1], $lech, $opponent)['points'] : null,
                ];
            }
        }

        return $grid;
    }

    /**
     * Mapa typów: ile razy typowano każdy wynik (gole powyżej GRID liczą się jako GRID, czyli „4+”).
     *
     * @param  iterable<array{0: int, 1: int}>  $tips
     * @return array{grid: array<int, array<int, int>>, max: int, count: int}
     */
    public static function tipMap(iterable $tips): array
    {
        $grid = array_fill(0, self::GRID + 1, array_fill(0, self::GRID + 1, 0));
        $count = 0;
        foreach ($tips as [$lech, $opponent]) {
            $grid[min($lech, self::GRID)][min($opponent, self::GRID)]++;
            $count++;
        }

        return ['grid' => $grid, 'max' => max(1, ...array_map('max', $grid)), 'count' => $count];
    }

    /** Kolejka do „co jeśli” i mapy typów: najbliższa nierozliczona z terminem, a gdy brak, ostatnia rozegrana. */
    public static function focusMatchday(Season $season): ?Matchday
    {
        $matchdays = Matchday::where('season_id', $season->id)->orderBy('number')->get();

        return $matchdays->first(fn (Matchday $m) => $m->status === MatchdayStatus::Planned && $m->kickoff_at !== null)
            ?? $matchdays->where('status', MatchdayStatus::Played)->last();
    }

    /**
     * Rywal w lidze w danej kolejce i jego typ (typ dopiero po pierwszym gwizdku, żeby nie zdradzać go wcześniej).
     *
     * @return array{name: string, tip: array{0: int, 1: int}|null}|null
     */
    public static function rival(User $user, Matchday $matchday): ?array
    {
        $team = SeasonTeam::where('season_id', $matchday->season_id)->where('user_id', $user->id)->first();
        if (! $team) {
            return null;
        }

        $entries = CompetitionEntry::where('season_team_id', $team->id)
            ->whereHas('competition', fn ($q) => $q->whereIn('type', [CompetitionType::League->value, CompetitionType::Swiss->value]))
            ->pluck('id');

        $fixture = Fixture::where('round', $matchday->number)
            ->where(fn ($q) => $q->whereIn('home_entry_id', $entries)->orWhereIn('away_entry_id', $entries))
            ->first();

        $otherEntry = $fixture ? ($entries->contains($fixture->home_entry_id) ? $fixture->away_entry_id : $fixture->home_entry_id) : null;
        $other = $otherEntry ? CompetitionEntry::with('seasonTeam.user:id,name,team_name', 'seasonTeam.bot:id,name')->find($otherEntry)?->seasonTeam : null;

        if (! $other) {
            return null;
        }

        $tip = null;
        if (! $matchday->isOpenForTips()) {
            $row = $other->user_id
                ? Tip::where('matchday_id', $matchday->id)->where('user_id', $other->user_id)->first(['lech_goals', 'opponent_goals'])
                : BotTip::where('matchday_id', $matchday->id)->where('season_team_id', $other->id)->first(['lech_goals', 'opponent_goals']);
            $tip = $row ? [(int) $row->lech_goals, (int) $row->opponent_goals] : null;
        }

        return ['name' => $other->name, 'tip' => $tip];
    }

    /**
     * Typy graczy w kolejce (bez botów), opcjonalnie tylko z ligi gracza.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function tips(Matchday $matchday, ?User $leagueOf = null): array
    {
        $query = Tip::where('matchday_id', $matchday->id);

        if ($leagueOf) {
            $team = SeasonTeam::where('season_id', $matchday->season_id)->where('user_id', $leagueOf->id)->first();
            $competitionIds = $team ? CompetitionEntry::where('season_team_id', $team->id)
                ->whereHas('competition', fn ($q) => $q->whereIn('type', [CompetitionType::League->value, CompetitionType::Swiss->value]))
                ->pluck('competition_id') : collect();
            $userIds = SeasonTeam::whereIn('id', CompetitionEntry::whereIn('competition_id', $competitionIds)->select('season_team_id'))
                ->whereNotNull('user_id')->pluck('user_id');
            $query->whereIn('user_id', $userIds);
        }

        return $query->get(['lech_goals', 'opponent_goals'])
            ->map(fn (Tip $t) => [(int) $t->lech_goals, (int) $t->opponent_goals])
            ->values()
            ->all();
    }
}
