<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Enums\League;
use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odznaki za osiągnięcia (20.11.2026): czysty prestiż, bez punktów do Hall of Fame i tabel. Tylko gracze (bez botów).
 * Liczymy je od zera z danych gry (typy, pytania, mecze w grze, kartki, zawodnicy, trofea, zaproszenia, polubienia)
 * po każdej rozliczonej kolejce i po zakończeniu sezonu. Odznaki z licznikiem mają trzy stopnie (brąz, srebro, złoto),
 * jednorazowe jeden. Lista do akceptacji: notes/odznaki (propozycja z 20.11.2026).
 */
final class Badges
{
    /** Kolory stopni: brąz, srebro, złoto. */
    public const TIER_COLORS = [1 => '#b7793e', 2 => '#9ca3af', 3 => '#eab308'];

    /** Odznaka „Prorok”: dokładny wynik, który typowało mniej niż tyle procent graczy. */
    private const PROPHET_PERCENT = 5;

    /** Odznaka „Pod prąd”: zwycięzcę (albo remis) typowało najwyżej tyle procent graczy. */
    private const AGAINST_PERCENT = 33;

    /** Odznaka „Kanonada”: tyle goli w jednym meczu. */
    private const GOALS_FEAST = 5;

    /**
     * Opis odznak: grupa, ikona (x-ui.icon), nazwa, za co i progi stopni.
     *
     * @return array<string, array{group: string, icon: string, label: string, rule: string, tiers: list<int>}>
     */
    public static function definitions(): array
    {
        return [
            'prophet' => ['group' => 'tips', 'icon' => 'eye', 'label' => __('Prophet'), 'rule' => __('An exact score tipped by fewer than 5% of players'), 'tiers' => [1, 3, 5]],
            'against' => ['group' => 'tips', 'icon' => 'bolt', 'label' => __('Against the tide'), 'rule' => __('The right winner or draw, backed by at most 33% of players'), 'tiers' => [1, 3, 5]],
            'sniper' => ['group' => 'tips', 'icon' => 'fire', 'label' => __('Tipping sniper'), 'rule' => __('Exact scores of the Lech match in one season'), 'tiers' => [3, 6, 9]],
            'streak' => ['group' => 'tips', 'icon' => 'link', 'label' => __('Streak'), 'rule' => __('Correct outcomes in consecutive matchdays'), 'tiers' => [3, 5, 9]],
            'full_house' => ['group' => 'tips', 'icon' => 'check-badge', 'label' => __('Full house'), 'rule' => __('The exact score and every question of a set right in one matchday'), 'tiers' => [1, 3, 5]],
            'know_it_all' => ['group' => 'tips', 'icon' => 'academic-cap', 'label' => __('Know-it-all'), 'rule' => __('Every question of a set right, matchdays in one season'), 'tiers' => [3, 6, 9]],
            'iron_fan' => ['group' => 'tips', 'icon' => 'calendar-days', 'label' => __('Iron fan'), 'rule' => __('A tip in every matchday of a season'), 'tiers' => [1, 3, 5]],
            'early_bird' => ['group' => 'tips', 'icon' => 'sun', 'label' => __('Early bird'), 'rule' => __('The first tip of the whole matchday'), 'tiers' => [1, 3, 5]],

            'goal_feast' => ['group' => 'matches', 'icon' => 'swords', 'label' => __('Goal feast'), 'rule' => __('5 or more goals in one game'), 'tiers' => [1, 3, 5]],
            'wall' => ['group' => 'matches', 'icon' => 'shield', 'label' => __('The wall'), 'rule' => __('Games without a goal conceded in one season'), 'tiers' => [3, 6, 9]],
            'unbeaten' => ['group' => 'matches', 'icon' => 'shield-check', 'label' => __('Unbeaten'), 'rule' => __('A league season without a defeat'), 'tiers' => [1]],
            'perfect' => ['group' => 'matches', 'icon' => 'star', 'label' => __('Perfect season'), 'rule' => __('Every league game of a season won'), 'tiers' => [1]],
            'fair_play' => ['group' => 'matches', 'icon' => 'hand-raised', 'label' => __('Fair play'), 'rule' => __('A season without a red card of your players'), 'tiers' => [1, 3, 5]],
            'scout' => ['group' => 'matches', 'icon' => 'users', 'label' => __('Talent scout'), 'rule' => __('Goals of the Lech players you fielded'), 'tiers' => [3, 10, 25]],

            'first_trophy' => ['group' => 'seasons', 'icon' => 'trophy', 'label' => __('First trophy'), 'rule' => __('The first trophy in your cabinet'), 'tiers' => [1]],
            'collector' => ['group' => 'seasons', 'icon' => 'archive-box', 'label' => __('Collector'), 'rule' => __('Different kinds of trophies'), 'tiers' => [3, 6, 10]],
            'climber' => ['group' => 'seasons', 'icon' => 'arrow-trending-up', 'label' => __('Climber'), 'rule' => __('Promotions in a row'), 'tiers' => [1, 2, 3]],
            'giant_killer' => ['group' => 'seasons', 'icon' => 'battle', 'label' => __('Giant killer'), 'rule' => __('A cup win against a team from a higher league'), 'tiers' => [1, 3, 5]],
            'legend' => ['group' => 'seasons', 'icon' => 'sparkles', 'label' => __('Legend'), 'rule' => __('The whole Legends League season without being knocked out'), 'tiers' => [1]],
            'veteran' => ['group' => 'seasons', 'icon' => 'clock', 'label' => __('Veteran'), 'rule' => __('Seasons played'), 'tiers' => [3, 5, 10]],

            'ambassador' => ['group' => 'community', 'icon' => 'megaphone', 'label' => __('Ambassador'), 'rule' => __('Invited players with a confirmed account'), 'tiers' => [3, 10, 25]],
            'favourite' => ['group' => 'community', 'icon' => 'heart', 'label' => __('Favourite'), 'rule' => __('Likes of your club'), 'tiers' => [10, 50, 100]],
        ];
    }

    /** @return array<string, string> grupa => nazwa */
    public static function groups(): array
    {
        return [
            'tips' => __('Tipping'),
            'matches' => __('Games in the game'),
            'seasons' => __('Seasons and competitions'),
            'community' => __('Community'),
        ];
    }

    /** Stopień dla postępu: ile progów osiągnięto. */
    public static function tierFor(string $badge, int $progress): int
    {
        $tiers = self::definitions()[$badge]['tiers'] ?? [];

        return count(array_filter($tiers, fn (int $threshold) => $progress >= $threshold));
    }

    /** Kolor stopnia; odznaki jednorazowe zawsze złote. */
    public static function color(string $badge, int $tier): string
    {
        $max = count(self::definitions()[$badge]['tiers'] ?? [1]);

        return self::TIER_COLORS[$max === 1 ? 3 : max(1, min(3, $tier))];
    }

    /**
     * Odznaki gracza z postępem: klucz => definicja + progress, tier, next (następny próg albo null).
     *
     * @return array<string, array{group: string, icon: string, label: string, rule: string, tiers: list<int>, progress: int, tier: int, next: ?int}>
     */
    public static function forUser(int $userId): array
    {
        $rows = UserBadge::where('user_id', $userId)->get()->keyBy('badge');

        $out = [];
        foreach (self::definitions() as $key => $definition) {
            $row = $rows->get($key);
            $progress = $row instanceof UserBadge ? $row->progress : 0;
            $tier = $row instanceof UserBadge ? $row->tier : 0;
            $out[$key] = $definition + ['progress' => $progress, 'tier' => $tier, 'next' => $definition['tiers'][$tier] ?? null];
        }

        return $out;
    }

    /**
     * Odznaki zdobyte (albo podniesione o stopień) po danej kolejce: lista do grafiki kolejki.
     *
     * @return list<array{name: string, badge: string, label: string, tier: int, icon: string}>
     */
    public static function earnedIn(Matchday $matchday): array
    {
        $definitions = self::definitions();

        return array_values(UserBadge::where('matchday_id', $matchday->id)->where('tier', '>', 0)
            ->with('user:id,name,team_name')
            ->orderByDesc('tier')
            ->get()
            ->filter(fn (UserBadge $row) => isset($definitions[$row->badge]))
            ->map(function (UserBadge $row) use ($definitions) {
                $user = $row->user;

                return [
                    'name' => $user instanceof User ? (string) ($user->team_name ?: $user->name) : '',
                    'badge' => $row->badge,
                    'label' => $definitions[$row->badge]['label'],
                    'tier' => $row->tier,
                    'icon' => $definitions[$row->badge]['icon'],
                ];
            })
            ->all());
    }

    /**
     * Przelicza odznaki wszystkich graczy od zera. Wzrost stopnia zapisuje z kolejką $matchday (null przy liczeniu wstecz).
     *
     * @return int liczba zapisanych wierszy
     */
    public static function rebuild(?Matchday $matchday = null): int
    {
        if (! Schema::hasTable('user_badges')) {
            return 0;
        }

        $progress = self::compute();
        $existing = UserBadge::all()->keyBy(fn (UserBadge $row) => $row->user_id.':'.$row->badge);
        $now = now();
        $saved = 0;

        foreach ($progress as $userId => $badges) {
            foreach ($badges as $badge => $value) {
                $tier = self::tierFor($badge, $value);
                $row = $existing->get($userId.':'.$badge);
                if ($row instanceof UserBadge) {
                    if ($row->progress === $value && $row->tier === $tier) {
                        continue;
                    }
                    $raised = $tier > $row->tier;
                    $row->fill(['progress' => $value, 'tier' => $tier]
                        + ($raised ? ['earned_at' => $now, 'matchday_id' => $matchday?->id] : []))->save();
                } else {
                    UserBadge::create(['user_id' => $userId, 'badge' => $badge, 'progress' => $value, 'tier' => $tier,
                        'earned_at' => $tier > 0 ? $now : null, 'matchday_id' => $tier > 0 ? $matchday?->id : null]);
                }
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * Postęp każdej odznaki: user_id => [odznaka => wartość]. Zera pomijamy.
     *
     * @return array<int, array<string, int>>
     */
    public static function compute(): array
    {
        $out = [];
        $put = function (int $userId, string $badge, int $value) use (&$out): void {
            if ($value > 0) {
                $out[$userId][$badge] = max($out[$userId][$badge] ?? 0, $value);
            }
        };

        self::tips($put);
        self::matches($put);
        self::seasons($put);
        self::community($put);

        return $out;
    }

    /**
     * Rozegrane kolejki z wynikiem, od najstarszej: id => [season_id, wynik].
     *
     * @return array<int, array{season: int, lech: int, opponent: int}>
     */
    private static function playedMatchdays(): array
    {
        $rows = DB::table('matchdays')
            ->join('seasons', 'seasons.id', '=', 'matchdays.season_id')
            ->where('matchdays.status', MatchdayStatus::Played->value)
            ->whereNotNull('matchdays.lech_goals')->whereNotNull('matchdays.opponent_goals')
            ->orderBy('seasons.number')->orderBy('matchdays.number')
            ->get(['matchdays.id', 'matchdays.season_id', 'matchdays.lech_goals', 'matchdays.opponent_goals']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = ['season' => (int) $row->season_id, 'lech' => (int) $row->lech_goals, 'opponent' => (int) $row->opponent_goals];
        }

        return $out;
    }

    /** @return list<int> id zakończonych sezonów */
    private static function finishedSeasons(): array
    {
        return array_values(array_map('intval', DB::table('seasons')->where('status', SeasonStatus::Finished->value)->pluck('id')->all()));
    }

    /** Typy i pytania: prorok, pod prąd, snajper, seria, komplet, wszystkowiedzący, żelazny kibic, ranny ptaszek. */
    private static function tips(callable $put): void
    {
        $played = self::playedMatchdays();
        if ($played === []) {
            return;
        }

        $tips = DB::table('tips')->whereIn('matchday_id', array_keys($played))
            ->get(['matchday_id', 'user_id', 'lech_goals', 'opponent_goals', 'save_order']);

        // Kolejka => typy; gracz => kolejka => [rozstrzygnięcie, dokładny].
        $byMatchday = [];
        foreach ($tips as $tip) {
            $byMatchday[(int) $tip->matchday_id][] = $tip;
        }

        $hits = [];
        $counts = ['prophet' => [], 'against' => [], 'early_bird' => []];
        foreach ($byMatchday as $matchdayId => $list) {
            $real = $played[$matchdayId];
            $total = count($list);
            $scores = [];
            $outcomes = [];
            foreach ($list as $tip) {
                $scores[$tip->lech_goals.':'.$tip->opponent_goals] = ($scores[$tip->lech_goals.':'.$tip->opponent_goals] ?? 0) + 1;
                $sign = $tip->lech_goals <=> $tip->opponent_goals;
                $outcomes[$sign] = ($outcomes[$sign] ?? 0) + 1;
            }

            $first = null;
            foreach ($list as $tip) {
                $userId = (int) $tip->user_id;
                $result = Scoring::tip((int) $tip->lech_goals, (int) $tip->opponent_goals, $real['lech'], $real['opponent']);
                $hits[$userId][$matchdayId] = $result;

                if ($result['exact'] && self::PROPHET_PERCENT * $total > $scores[$tip->lech_goals.':'.$tip->opponent_goals] * 100) {
                    $counts['prophet'][$userId] = ($counts['prophet'][$userId] ?? 0) + 1;
                }
                if ($result['outcome'] && self::AGAINST_PERCENT * $total >= ($outcomes[$tip->lech_goals <=> $tip->opponent_goals] ?? 0) * 100) {
                    $counts['against'][$userId] = ($counts['against'][$userId] ?? 0) + 1;
                }
                if ($tip->save_order !== null && ($first === null || (int) $tip->save_order < (int) $first->save_order)) {
                    $first = $tip;
                }
            }
            if ($first !== null) {
                $counts['early_bird'][(int) $first->user_id] = ($counts['early_bird'][(int) $first->user_id] ?? 0) + 1;
            }
        }

        foreach ($counts as $badge => $users) {
            foreach ($users as $userId => $value) {
                $put($userId, $badge, $value);
            }
        }

        $perfectSets = self::perfectQuestionSets(array_keys($played));
        $finished = self::finishedSeasons();
        $matchdaysPerSeason = [];
        foreach ($played as $id => $matchday) {
            $matchdaysPerSeason[$matchday['season']][] = $id;
        }

        foreach ($hits as $userId => $matchdays) {
            // Snajper: najlepszy sezon pod względem dokładnych wyników.
            $exactBySeason = [];
            foreach ($matchdays as $matchdayId => $result) {
                if ($result['exact']) {
                    $season = $played[$matchdayId]['season'];
                    $exactBySeason[$season] = ($exactBySeason[$season] ?? 0) + 1;
                }
            }
            $put($userId, 'sniper', $exactBySeason === [] ? 0 : max($exactBySeason));

            // Seria: kolejne rozegrane kolejki z trafionym rozstrzygnięciem (brak typu przerywa serię).
            $streak = 0;
            $best = 0;
            foreach (array_keys($played) as $matchdayId) {
                $streak = ($matchdays[$matchdayId]['outcome'] ?? false) ? $streak + 1 : 0;
                $best = max($best, $streak);
            }
            $put($userId, 'streak', $best);

            // Komplet i wszystkowiedzący (pytania z zestawu bez błędu).
            $perfect = $perfectSets[$userId] ?? [];
            $full = 0;
            $knowBySeason = [];
            foreach ($perfect as $matchdayId) {
                $season = $played[$matchdayId]['season'];
                $knowBySeason[$season] = ($knowBySeason[$season] ?? 0) + 1;
                if ($matchdays[$matchdayId]['exact'] ?? false) {
                    $full++;
                }
            }
            $put($userId, 'full_house', $full);
            $put($userId, 'know_it_all', $knowBySeason === [] ? 0 : max($knowBySeason));

            // Żelazny kibic: typ w każdej rozegranej kolejce zakończonego sezonu.
            $iron = 0;
            foreach ($finished as $seasonId) {
                $ids = $matchdaysPerSeason[$seasonId] ?? [];
                if ($ids !== [] && count(array_intersect_key($matchdays, array_flip($ids))) === count($ids)) {
                    $iron++;
                }
            }
            $put($userId, 'iron_fan', $iron);
        }
    }

    /**
     * Kolejki, w których gracz trafił wszystkie pytania co najmniej jednego zestawu: user_id => lista id kolejek.
     *
     * @param  list<int>  $matchdayIds
     * @return array<int, list<int>>
     */
    private static function perfectQuestionSets(array $matchdayIds): array
    {
        $questions = DB::table('matchday_questions')->whereIn('matchday_id', $matchdayIds)
            ->where('voided', false)->whereNotNull('correct')
            ->get(['id', 'matchday_id', 'score_set', 'correct'])
            ->keyBy('id');
        if ($questions->isEmpty()) {
            return [];
        }

        // Liczba pytań w zestawie kolejki.
        $setSize = [];
        foreach ($questions as $question) {
            $key = $question->matchday_id.':'.$question->score_set;
            $setSize[$key] = ($setSize[$key] ?? 0) + 1;
        }

        $right = [];
        $answers = DB::table('question_answers')->whereIn('matchday_question_id', $questions->keys())
            ->get(['matchday_question_id', 'user_id', 'answer']);
        foreach ($answers as $answer) {
            $question = $questions[$answer->matchday_question_id];
            if ((bool) $answer->answer === (bool) $question->correct) {
                $key = $question->matchday_id.':'.$question->score_set;
                $right[(int) $answer->user_id][$key] = ($right[(int) $answer->user_id][$key] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($right as $userId => $sets) {
            $matchdays = [];
            foreach ($sets as $key => $count) {
                if ($count === $setSize[$key]) {
                    $matchdays[(int) explode(':', $key)[0]] = true;
                }
            }
            $out[$userId] = array_keys($matchdays);
        }

        return $out;
    }

    /** Mecze w grze: kanonada, mur, niepokonany, komplet zwycięstw, fair play, łowca talentów. */
    private static function matches(callable $put): void
    {
        $finished = self::finishedSeasons();

        $fixtures = DB::table('fixtures')
            ->join('competitions', 'competitions.id', '=', 'fixtures.competition_id')
            ->whereNotNull('fixtures.home_goals')->whereNotNull('fixtures.away_goals')
            ->get(['fixtures.home_entry_id', 'fixtures.away_entry_id', 'fixtures.home_goals', 'fixtures.away_goals',
                'fixtures.winner_entry_id', 'competitions.id as competition_id', 'competitions.type', 'competitions.season_id']);

        $entries = DB::table('competition_entries')
            ->join('season_teams', 'season_teams.id', '=', 'competition_entries.season_team_id')
            ->whereNotNull('season_teams.user_id')
            ->get(['competition_entries.id', 'competition_entries.eliminated_round', 'season_teams.user_id', 'season_teams.position'])
            ->keyBy('id');
        $positions = DB::table('competition_entries')
            ->join('season_teams', 'season_teams.id', '=', 'competition_entries.season_team_id')
            ->pluck('season_teams.position', 'competition_entries.id');

        $feast = [];
        $wallBySeason = [];
        $league = [];
        $giant = [];
        $cups = [CompetitionType::Cup->value, CompetitionType::Champions->value, CompetitionType::Europa->value, CompetitionType::Conference->value];
        foreach ($fixtures as $f) {
            foreach ([[$f->home_entry_id, (int) $f->home_goals, (int) $f->away_goals, $f->away_entry_id], [$f->away_entry_id, (int) $f->away_goals, (int) $f->home_goals, $f->home_entry_id]] as [$entryId, $for, $against, $opponentId]) {
                $entry = $entries[$entryId] ?? null;
                if ($entry === null) {
                    continue;
                }
                $userId = (int) $entry->user_id;
                if ($for >= self::GOALS_FEAST) {
                    $feast[$userId] = ($feast[$userId] ?? 0) + 1;
                }
                if ($against === 0) {
                    $wallBySeason[$userId][(int) $f->season_id] = ($wallBySeason[$userId][(int) $f->season_id] ?? 0) + 1;
                }
                if (in_array($f->type, [CompetitionType::League->value, CompetitionType::Swiss->value], true) && in_array((int) $f->season_id, $finished, true)) {
                    $row = $league[$userId][(int) $f->competition_id] ?? ['played' => 0, 'won' => 0, 'lost' => 0];
                    $league[$userId][(int) $f->competition_id] = [
                        'played' => $row['played'] + 1,
                        'won' => $row['won'] + (int) ($for > $against),
                        'lost' => $row['lost'] + (int) ($for < $against),
                    ];
                }
                // Pogromca: wygrana w pucharze z zespołem z wyższej ligi (mniejszy numer poziomu).
                if (in_array($f->type, $cups, true) && (int) $f->winner_entry_id === (int) $entryId && isset($positions[$opponentId])) {
                    $mine = League::forPosition((int) $entry->position)->value;
                    $theirs = League::forPosition((int) $positions[$opponentId])->value;
                    if ($theirs < $mine) {
                        $giant[$userId] = ($giant[$userId] ?? 0) + 1;
                    }
                }
            }
        }

        foreach ($feast as $userId => $value) {
            $put($userId, 'goal_feast', $value);
        }
        foreach ($wallBySeason as $userId => $seasons) {
            $put($userId, 'wall', max($seasons));
        }
        foreach ($giant as $userId => $value) {
            $put($userId, 'giant_killer', $value);
        }
        foreach ($league as $userId => $competitions) {
            foreach ($competitions as $row) {
                if ($row['played'] >= Matchday::PER_SEASON) {
                    $put($userId, 'unbeaten', (int) ($row['lost'] === 0));
                    $put($userId, 'perfect', (int) ($row['won'] === $row['played']));
                }
            }
        }

        // Liga Legend: cały zakończony sezon bez odpadnięcia.
        $legends = DB::table('competition_entries')
            ->join('competitions', 'competitions.id', '=', 'competition_entries.competition_id')
            ->join('season_teams', 'season_teams.id', '=', 'competition_entries.season_team_id')
            ->where('competitions.type', CompetitionType::Legends->value)
            ->whereIn('competitions.season_id', $finished)
            ->whereNull('competition_entries.eliminated_round')
            ->whereNotNull('season_teams.user_id')
            ->selectRaw('season_teams.user_id, COUNT(*) as seasons')
            ->groupBy('season_teams.user_id')
            ->get();
        foreach ($legends as $row) {
            $put((int) $row->user_id, 'legend', (int) $row->seasons);
        }

        // Fair play: zakończone sezony z dorobkiem i bez czerwonej kartki.
        $cards = DB::table('team_scores')
            ->join('matchdays', 'matchdays.id', '=', 'team_scores.matchday_id')
            ->whereIn('matchdays.season_id', $finished)
            ->whereNotNull('team_scores.user_id')
            ->selectRaw('team_scores.user_id, matchdays.season_id, SUM(team_scores.red_cards) as red')
            ->groupBy('team_scores.user_id', 'matchdays.season_id')
            ->get();
        $fair = [];
        foreach ($cards as $row) {
            if ((int) $row->red === 0) {
                $fair[(int) $row->user_id] = ($fair[(int) $row->user_id] ?? 0) + 1;
            }
        }
        foreach ($fair as $userId => $value) {
            $put($userId, 'fair_play', $value);
        }

        // Łowca talentów: gole wystawionych zawodników (Gwiazdy gry, wiersze wszystkich sezonów).
        if (Schema::hasTable('star_stats')) {
            $goals = DB::table('star_stats')->where('season_id', 0)
                ->selectRaw('user_id, SUM(goals) as goals')->groupBy('user_id')->get();
            foreach ($goals as $row) {
                $put((int) $row->user_id, 'scout', (int) $row->goals);
            }
        }
    }

    /** Sezony: pierwsze trofeum, kolekcjoner, wspinacz, weteran. */
    private static function seasons(callable $put): void
    {
        $trophies = DB::table('hall_of_fame_awards')->whereNotNull('user_id')->whereNotNull('trophy')
            ->selectRaw('user_id, COUNT(*) as total, COUNT(DISTINCT trophy) as kinds')->groupBy('user_id')->get();
        foreach ($trophies as $row) {
            $put((int) $row->user_id, 'first_trophy', (int) ((int) $row->total > 0));
            $put((int) $row->user_id, 'collector', (int) $row->kinds);
        }

        $teams = DB::table('season_teams')
            ->join('seasons', 'seasons.id', '=', 'season_teams.season_id')
            ->whereNotNull('season_teams.user_id')
            ->where('seasons.status', '!=', SeasonStatus::Draft->value)
            ->orderBy('seasons.number')
            ->get(['season_teams.user_id', 'season_teams.position', 'seasons.number']);

        $history = [];
        foreach ($teams as $row) {
            $history[(int) $row->user_id][(int) $row->number] = League::forPosition((int) $row->position)->value;
        }

        foreach ($history as $userId => $tiers) {
            $put($userId, 'veteran', count($tiers));

            // Wspinacz: awanse (wyższa liga w kolejnym sezonie) jeden po drugim.
            $streak = 0;
            $best = 0;
            $previous = null;
            foreach ($tiers as $number => $tier) {
                if ($previous !== null && $previous[0] === $number - 1 && $tier < $previous[1]) {
                    $streak++;
                } else {
                    $streak = 0;
                }
                $best = max($best, $streak);
                $previous = [$number, $tier];
            }
            $put($userId, 'climber', $best);
        }
    }

    /** Społeczność: ambasador (zaproszenia), ulubieniec (polubienia klubu). */
    private static function community(callable $put): void
    {
        $invited = DB::table('users')->whereNotNull('referred_by')->whereNotNull('email_verified_at')
            ->selectRaw('referred_by, COUNT(*) as total')->groupBy('referred_by')->get();
        foreach ($invited as $row) {
            $put((int) $row->referred_by, 'ambassador', (int) $row->total);
        }

        $likes = DB::table('team_likes')->selectRaw('user_id, COUNT(*) as total')->groupBy('user_id')->get();
        foreach ($likes as $row) {
            $put((int) $row->user_id, 'favourite', (int) $row->total);
        }
    }
}
