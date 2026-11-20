<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Enums\League;
use App\Enums\MatchdayStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamScore;

/**
 * Wyścig o nagrody indywidualne na żywo (strona /nagrody i zakładka „Nagrody” na profilu drużyny).
 * Liczy to samo co AwardHallOfFame::individual po sezonie, ale z rozliczonych kolejek bieżącego sezonu:
 *  - MVP: trafione rozstrzygnięcia + punkty ataku i obrony zawodników w meczach ligowych,
 *  - Złota Piłka: gole (ofensywa) ze wszystkich rozgrywek,
 *  - Złoty But: punkty ataku zawodników ze wszystkich rozgrywek,
 *  - Złote Rękawice: punkty obrony zawodników w meczach ligowych.
 * Liczą się kolejki obecnego właściciela miejsca (nie bota, za którego wskoczył gracz). Remis w trakcie sezonu:
 * wyższa liga, potem wyższa pozycja na liście (kryterium trofeów rozstrzyga dopiero po sezonie).
 * Zmiana miejsca = porównanie z klasyfikacją bez ostatniej rozliczonej kolejki.
 * Królowie strzelców: najwięcej bramek w tabeli ligi, przy remisie wyższe miejsce (jak po sezonie).
 * Wyniki w PageCache (czyszczonym po rozliczeniu kolejki).
 */
final class AwardRace
{
    /** Nagroda => kolumna kryterium. */
    public const AWARDS = [
        'mvp' => 'mvp',
        'golden_ball' => 'goals',
        'golden_boot' => 'boot',
        'golden_gloves' => 'gloves',
    ];

    /** @var array<string, mixed> */
    private static array $memo = [];

    /**
     * Opis nagród do widoków: nazwa, ikona, gradient, krótkie kryterium, zasada i jednostka.
     *
     * @return array<string, array{label: string, icon: string, grad: string, short: string, rule: string, unit: string}>
     */
    public static function meta(): array
    {
        return [
            'mvp' => ['label' => __('Season MVP'), 'icon' => 'star', 'grad' => 'from-indigo-500 to-violet-600',
                'short' => __('Outcomes + attack + defence in leagues'),
                'rule' => __('Correct outcomes plus your players\' attack and defence points in league matches.'), 'unit' => __('Pts')],
            'golden_ball' => ['label' => __('Golden Ball'), 'icon' => 'sparkles', 'grad' => 'from-amber-400 to-yellow-600',
                'short' => __('Goals in all competitions'),
                'rule' => __('The most goals in all competitions (points for the tip plus your players\' attack points, without the rival\'s own goals).'), 'unit' => __('Goals')],
            'golden_boot' => ['label' => __('Golden Boot'), 'icon' => 'fire', 'grad' => 'from-orange-500 to-red-600',
                'short' => __('Players\' attack points'),
                'rule' => __('The most attack points of your players (goals and assists) in all competitions.'), 'unit' => __('Pts')],
            'golden_gloves' => ['label' => __('Golden Gloves'), 'icon' => 'shield-check', 'grad' => 'from-teal-500 to-emerald-600',
                'short' => __('Defence points in leagues'),
                'rule' => __('The most defence points of your players (clean sheets) in league matches.'), 'unit' => __('Pts')],
        ];
    }

    /** Sezon wyścigu: bieżący, a bez niego ostatni. */
    public static function season(): ?Season
    {
        return Season::current() ?? Season::orderByDesc('number')->first();
    }

    /**
     * Zespoły sezonu z dorobkiem (teraz i bez ostatniej kolejki), numer ostatniej rozliczonej kolejki i liczba kolejek do końca.
     *
     * @return array{last: int|null, remaining: int, rows: array<int, array<string, mixed>>}
     */
    public static function data(Season $season): array
    {
        return self::$memo['data:'.$season->id] ??= PageCache::remember('awards:'.$season->id, fn () => self::build($season));
    }

    /**
     * Klasyfikacja jednej nagrody: wiersze z miejscem (rank), wartością (value) i zmianą miejsca (move, null bez porównania).
     *
     * @return list<array<string, mixed>>
     */
    public static function ranking(Season $season, string $award): array
    {
        return self::$memo['ranking:'.$season->id.':'.$award] ??= self::rank(self::data($season), $award);
    }

    /**
     * Wycinek klasyfikacji wokół zespołu: miejsce zespołu i sąsiedzi (albo podium, gdy zespół jest wysoko).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function around(array $rows, int $teamId, int $radius = 2): array
    {
        $index = array_search($teamId, array_column($rows, 'team_id'), true);
        if ($index === false) {
            return [];
        }

        $start = max(0, min($index - $radius, count($rows) - (2 * $radius + 1)));
        $start = $index <= $radius + 1 ? 0 : $start; // blisko podium: od pierwszego miejsca

        return array_slice($rows, $start, 2 * $radius + 1);
    }

    /**
     * Ligi sezonu do klasyfikacji strzelców: id rozgrywek => [nazwa, poziom].
     *
     * @return array<int, array{name: string, tier: int}>
     */
    public static function leagues(Season $season): array
    {
        return self::$memo['leagues:'.$season->id] ??= Competition::where('season_id', $season->id)
            ->whereIn('type', [CompetitionType::League->value, CompetitionType::Swiss->value])
            ->get()
            ->sortBy(fn (Competition $c) => [(int) $c->leagueTier(), (int) $c->division])
            ->mapWithKeys(fn (Competition $c) => [$c->id => [
                'name' => $c->name ?: (League::tryFrom((int) $c->leagueTier())?->label() ?? $c->type->label()),
                'tier' => (int) $c->leagueTier(),
            ]])
            ->all();
    }

    /** Liga (id rozgrywek ligowych), w której gra zespół w sezonie, albo null. */
    public static function leagueOf(Season $season, int $teamId): ?int
    {
        $id = CompetitionEntry::where('season_team_id', $teamId)->whereIn('competition_id', array_keys(self::leagues($season)))->value('competition_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Klasyfikacja strzelców ligi: bramki z tabeli, przy remisie wyższe miejsce.
     *
     * @return list<array<string, mixed>>
     */
    public static function scorers(Competition $competition): array
    {
        return self::$memo['scorers:'.$competition->id] ??= PageCache::remember('awards:scorers:'.$competition->id, function () use ($competition): array {
            $rows = [];
            foreach (Standings::for($competition)->values() as $index => $row) {
                $team = $row['team'];
                if (! $team || (! $team->user_id && ! $team->bot_id)) {
                    continue;
                }
                $rows[] = [
                    'team_id' => $team->id,
                    'user_id' => $team->user_id,
                    'bot' => $team->bot_id !== null && $team->user_id === null,
                    'name' => $team->name,
                    'url' => $team->profileUrl(),
                    'tier' => (int) $competition->leagueTier(),
                    'value' => (int) $row['for'],
                    'played' => (int) $row['played'],
                    'place' => $index + 1,
                ];
            }

            usort($rows, fn ($a, $b) => [$b['value'], $a['place']] <=> [$a['value'], $b['place']]);

            return array_map(fn ($row, $i) => ['rank' => $i + 1, 'move' => null] + $row, $rows, array_keys($rows));
        });
    }

    /** @return array{last: int|null, remaining: int, rows: array<int, array<string, mixed>>} */
    private static function build(Season $season): array
    {
        $teams = SeasonTeam::where('season_id', $season->id)
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('bot_id'))
            ->with(['user:id,name,team_name', 'bot:id,name'])
            ->get()
            ->keyBy('id');

        $remaining = Matchday::where('season_id', $season->id)->where('status', '!=', MatchdayStatus::Played->value)->count();

        // Ostatnia rozliczona kolejka (z dorobkiem).
        $last = TeamScore::query()
            ->join('matchdays', 'matchdays.id', '=', 'team_scores.matchday_id')
            ->where('matchdays.season_id', $season->id)
            ->orderByDesc('matchdays.number')
            ->first(['matchdays.id as last_id', 'matchdays.number as last_number']);
        $lastId = $last ? (int) $last->getAttribute('last_id') : 0;

        $rows = [];
        foreach ($teams as $team) {
            $rows[$team->id] = [
                'team_id' => $team->id,
                'user_id' => $team->user_id,
                'bot' => $team->user_id === null,
                'name' => $team->name,
                'url' => $team->profileUrl(),
                'tier' => $team->league->value,
                'position' => $team->position,
                'outcomes' => 0, 'attack' => 0, 'defense' => 0, 'mvp' => 0, 'goals' => 0, 'boot' => 0, 'gloves' => 0,
                'prev' => ['mvp' => 0, 'goals' => 0, 'boot' => 0, 'gloves' => 0],
            ];
        }

        if ($rows !== [] && $lastId > 0) {
            $league = CompetitionType::League->value;
            // Sumy z kolejek sezonu; „p_” = bez ostatniej kolejki (do strzałek zmiany miejsca).
            $sums = TeamScore::query()
                ->join('matchdays', 'matchdays.id', '=', 'team_scores.matchday_id')
                ->where('matchdays.season_id', $season->id)
                ->whereIn('team_scores.season_team_id', array_keys($rows))
                ->groupBy('team_scores.season_team_id', 'team_scores.user_id', 'team_scores.bot_id')
                ->selectRaw('team_scores.season_team_id as team_id, team_scores.user_id as owner_user, team_scores.bot_id as owner_bot,
                    sum(team_scores.offense) as goals,
                    sum(team_scores.offense_bonus) as boot,
                    sum(case when team_scores.score_set = ? then team_scores.offense_bonus else 0 end) as attack,
                    sum(case when team_scores.score_set = ? then team_scores.defense_bonus else 0 end) as defense,
                    sum(case when team_scores.score_set = ? and team_scores.outcome_hit then 1 else 0 end) as outcomes,
                    sum(case when team_scores.matchday_id <> ? then team_scores.offense else 0 end) as p_goals,
                    sum(case when team_scores.matchday_id <> ? then team_scores.offense_bonus else 0 end) as p_boot,
                    sum(case when team_scores.matchday_id <> ? and team_scores.score_set = ? then team_scores.offense_bonus else 0 end) as p_attack,
                    sum(case when team_scores.matchday_id <> ? and team_scores.score_set = ? then team_scores.defense_bonus else 0 end) as p_defense,
                    sum(case when team_scores.matchday_id <> ? and team_scores.score_set = ? and team_scores.outcome_hit then 1 else 0 end) as p_outcomes',
                    [$league, $league, $league, $lastId, $lastId, $lastId, $league, $lastId, $league, $lastId, $league])
                ->toBase()
                ->get();

            foreach ($sums as $sum) {
                $team = $teams->get((int) $sum->team_id);
                // Tylko kolejki obecnego właściciela miejsca.
                if (! $team || (int) $sum->owner_user !== (int) $team->user_id || (int) $sum->owner_bot !== (int) $team->bot_id) {
                    continue;
                }
                $row = &$rows[$team->id];
                $row['outcomes'] = (int) $sum->outcomes;
                $row['attack'] = (int) $sum->attack;
                $row['defense'] = (int) $sum->defense;
                $row['mvp'] = $row['outcomes'] + $row['attack'] + $row['defense'];
                $row['goals'] = (int) $sum->goals;
                $row['boot'] = (int) $sum->boot;
                $row['gloves'] = $row['defense'];
                $row['prev'] = [
                    'mvp' => (int) $sum->p_outcomes + (int) $sum->p_attack + (int) $sum->p_defense,
                    'goals' => (int) $sum->p_goals,
                    'boot' => (int) $sum->p_boot,
                    'gloves' => (int) $sum->p_defense,
                ];
                unset($row);
            }
        }

        return ['last' => $last ? (int) $last->getAttribute('last_number') : null, 'remaining' => $remaining, 'rows' => $rows];
    }

    /**
     * @param  array{last: int|null, remaining: int, rows: array<int, array<string, mixed>>}  $data
     * @return list<array<string, mixed>>
     */
    private static function rank(array $data, string $award): array
    {
        $column = self::AWARDS[$award] ?? 'mvp';
        $sort = function (array $rows, bool $previous) use ($column): array {
            usort($rows, function (array $a, array $b) use ($column, $previous): int {
                $va = $previous ? $a['prev'][$column] : $a[$column];
                $vb = $previous ? $b['prev'][$column] : $b[$column];

                return [$vb, $a['tier'], $a['position']] <=> [$va, $b['tier'], $b['position']];
            });

            return $rows;
        };

        $rows = array_values($data['rows']);
        // Porównanie tylko, gdy przed ostatnią kolejką już coś było rozliczone.
        $previous = [];
        if (array_filter($rows, fn (array $row) => $row['prev'][$column] > 0) !== []) {
            foreach ($sort($rows, true) as $i => $row) {
                $previous[$row['team_id']] = $i + 1;
            }
        }

        $out = [];
        foreach ($sort($rows, false) as $i => $row) {
            $rank = $i + 1;
            $out[] = ['rank' => $rank, 'value' => $row[$column], 'move' => isset($previous[$row['team_id']]) ? $previous[$row['team_id']] - $rank : null] + $row;
        }

        return $out;
    }
}
