<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\Rival;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamScore;
use App\Models\Tip;

/**
 * Dane do grafik kolejki w panelu (Promocja → Grafiki kolejki): zapowiedź w barwach drużyn, pytania,
 * typy kibiców, hity kolejki w grze, ostatnia prosta, wynik, „pod prąd” i wyścig o nagrody.
 * Liczymy tylko typy graczy (bez botów), tak jak w MatchdayInsights.
 */
final class MatchdayGraphics
{
    /** Granat gry, gdy rywala nie ma w panelu Rywale. */
    public const FALLBACK = ['#0b1f4d', '#86adff'];

    /** Najwyżej tylu graczy wymieniamy na grafice „Pod prąd”. */
    private const NAMES = 4;

    /** „Pod prąd”: zwycięzcę (albo remis) typowało najwyżej tyle procent graczy. */
    private const AGAINST_PERCENT = 33;

    /**
     * Gospodarz i gość: nazwa, skrót, barwy (#rrggbb) i kolor napisu na pierwszej barwie.
     *
     * @return array{home: array{name: string, short: string, colors: list<string>, ink: string}, away: array{name: string, short: string, colors: list<string>, ink: string}}
     */
    public static function teams(Matchday $matchday): array
    {
        $club = [
            'name' => (string) config('club.name'),
            'short' => mb_strtoupper(mb_substr((string) config('club.short_name'), 0, 3)),
            'colors' => Rival::LECH_COLORS,
        ];

        $opponent = (string) $matchday->opponent;
        $rival = Rival::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($opponent))])->first(['short_name']);
        $colors = Rival::colorsFor($opponent);
        $other = [
            'name' => $opponent,
            'short' => mb_strtoupper(filled($rival?->short_name) ? (string) $rival->short_name : mb_substr($opponent, 0, 3)),
            'colors' => $colors ?: self::FALLBACK,
        ];

        [$home, $away] = $matchday->is_home ? [$club, $other] : [$other, $club];

        return [
            'home' => $home + ['ink' => self::ink($home['colors'][0])],
            'away' => $away + ['ink' => self::ink($away['colors'][0])],
        ];
    }

    /** Czytelny kolor napisu na tle $hex: granat na jasnym, biały na ciemnym. */
    public static function ink(string $hex): string
    {
        [$r, $g, $b] = Graphic::rgb($hex);

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#0b1f4d' : '#ffffff';
    }

    /** Czy barwa jest tak jasna, że na jasnym pasku trzeba ją obrysować. */
    public static function isLight(string $hex): bool
    {
        return self::ink($hex) !== '#ffffff';
    }

    /**
     * Zestawy pytań kolejki: typ rozgrywek => pytania z odsetkiem odpowiedzi TAK (null, gdy nikt nie odpowiedział).
     *
     * @return array<string, list<array{text: string, yes: ?int, answers: int, correct: ?bool}>>
     */
    public static function questions(Matchday $matchday): array
    {
        $questions = MatchdayQuestion::where('matchday_id', $matchday->id)->where('voided', false)
            ->orderBy('score_set')->orderBy('position')->get(['id', 'score_set', 'text', 'correct']);

        $crowd = Questions::crowd($questions->pluck('id'));

        $sets = [];
        foreach ($questions as $question) {
            $sets[$question->score_set][] = [
                'text' => $question->text,
                'yes' => $crowd[$question->id]['yes'],
                'answers' => $crowd[$question->id]['answers'],
                'correct' => $question->correct,
            ];
        }

        return $sets;
    }

    /**
     * Ostatnia prosta: liczba typów, liczba graczy i odsetek tych, którzy już typowali.
     *
     * @return array{tips: int, players: int, percent: int, missing: int}
     */
    public static function progress(Matchday $matchday): array
    {
        $tips = Tip::where('matchday_id', $matchday->id)->count();
        $players = Players::eligible()->count();

        return [
            'tips' => $tips,
            'players' => $players,
            'percent' => $players > 0 ? min(100, (int) round($tips * 100 / $players)) : 0,
            'missing' => max(0, $players - $tips),
        ];
    }

    /**
     * Hity kolejki w grze, wybierane samodzielnie:
     *  - ligi: mecz drużyn z czołówki z najmniejszą różnicą punktów (najpierw najwyższa liga, potem jeszcze jeden z niższych),
     *  - puchar: pojedynek dwóch najwyżej notowanych zespołów (najlepsze miejsca na liście zespołów),
     *  - Liga Legend: starcie najwyżej notowanych graczy w tabeli.
     *
     * @return list<array{competition: string, home: string, away: string, home_note: string, away_note: string}>
     */
    public static function hits(Matchday $matchday): array
    {
        $competitions = Competition::where('season_id', $matchday->season_id)
            ->whereIn('type', [CompetitionType::League->value, CompetitionType::Cup->value, CompetitionType::Legends->value])
            ->where('division', 0)
            ->get();

        $leagues = [];
        $hits = [];
        foreach ($competitions as $competition) {
            $fixtures = Fixture::where('competition_id', $competition->id)->where('round', $matchday->number)
                ->with(['home.seasonTeam.user:id,name,team_name', 'home.seasonTeam.bot:id,name', 'away.seasonTeam.user:id,name,team_name', 'away.seasonTeam.bot:id,name'])
                ->get();
            if ($fixtures->isEmpty()) {
                continue;
            }

            if ($competition->type === CompetitionType::Cup) {
                // Miejsce na liście zespołów: im niżej numer, tym wyżej zespół.
                $best = $fixtures->filter(fn (Fixture $f) => self::team($f->home) && self::team($f->away))
                    ->sortBy(fn (Fixture $f) => (self::team($f->home)->position ?? 0) + (self::team($f->away)->position ?? 0))->first();
                if ($best) {
                    $hits['cup'] = self::row($competition->name, $best, '', '');
                }

                continue;
            }

            // Ligi i Liga Legend: tabela przed tą kolejką.
            $table = Standings::for($competition, null, $matchday->number - 1)->values();
            $places = [];
            foreach ($table as $i => $row) {
                $places[$row['entry_id']] = ['place' => $i + 1, 'points' => $row['points']];
            }

            $scored = $fixtures->filter(fn (Fixture $f) => isset($places[$f->home_entry_id], $places[$f->away_entry_id]))
                ->map(function (Fixture $f) use ($places) {
                    $home = $places[(int) $f->home_entry_id];
                    $away = $places[(int) $f->away_entry_id];

                    return ['fixture' => $f, 'home' => $home, 'away' => $away,
                        'score' => $home['place'] + $away['place'] + abs($home['points'] - $away['points'])];
                })
                ->sortBy('score');
            $best = $scored->first();
            if (! $best) {
                continue;
            }

            $note = fn (array $p) => $matchday->number > 1 ? $p['place'].'.' : '';
            $row = self::row($competition->name, $best['fixture'], $note($best['home']), $note($best['away']));

            if ($competition->type === CompetitionType::Legends) {
                $hits['legends'] = $row;
            } else {
                $leagues[] = ['tier' => (int) $competition->tier, 'score' => $best['score'], 'row' => $row];
            }
        }

        // Najwyższa liga zawsze, potem najciekawszy mecz z pozostałych lig.
        usort($leagues, fn ($a, $b) => $a['tier'] <=> $b['tier']);
        $top = array_shift($leagues);
        usort($leagues, fn ($a, $b) => [$a['score'], $a['tier']] <=> [$b['score'], $b['tier']]);
        $second = $leagues[0] ?? null;

        return array_values(array_filter([
            $top['row'] ?? null,
            $second['row'] ?? null,
            $hits['cup'] ?? null,
            $hits['legends'] ?? null,
        ]));
    }

    /** Zespół wpisu w rozgrywkach (albo dorobku kolejki); relacje bez typów generycznych, więc sprawdzamy klasę. */
    private static function team(mixed $model): ?SeasonTeam
    {
        $team = ($model instanceof CompetitionEntry || $model instanceof TeamScore) ? $model->seasonTeam : null;

        return $team instanceof SeasonTeam ? $team : null;
    }

    /** @return array{competition: string, home: string, away: string, home_note: string, away_note: string} */
    private static function row(string $competition, Fixture $fixture, string $homeNote, string $awayNote): array
    {
        return [
            'competition' => $competition,
            'home' => (string) self::team($fixture->home)?->name,
            'away' => (string) self::team($fixture->away)?->name,
            'home_note' => $homeNote,
            'away_note' => $awayNote,
        ];
    }

    /**
     * Najwięcej goli w kolejce (liga): trzy zespoły graczy z największym atakiem.
     *
     * @return list<array{name: string, goals: int}>
     */
    public static function scorers(Matchday $matchday): array
    {
        return array_values(TeamScore::where('matchday_id', $matchday->id)
            ->where('score_set', CompetitionType::League->value)
            ->whereNotNull('user_id')
            ->with(['seasonTeam.user:id,name,team_name', 'seasonTeam.bot:id,name'])
            ->orderByDesc('offense')
            ->orderBy('tip_order')
            ->limit(3)
            ->get()
            ->map(fn (TeamScore $s) => ['name' => (string) self::team($s)?->name, 'goals' => $s->offense])
            ->all());
    }

    /**
     * „Pod prąd”: wynik, którego zwycięzcę typowała mniejszość, i gracze, którzy trafili go dokładnie
     * (albo choć zwycięzcę). Null, gdy nie było niespodzianki albo meczu jeszcze nie rozliczono.
     *
     * @return array{percent: int, outcome: string, exact: bool, names: list<string>, more: int}|null
     */
    public static function against(Matchday $matchday): ?array
    {
        $lech = $matchday->lech_goals;
        $rival = $matchday->opponent_goals;
        if ($lech === null || $rival === null) {
            return null;
        }

        $tips = Tip::where('matchday_id', $matchday->id)
            ->join('users', 'users.id', '=', 'tips.user_id')
            ->get(['tips.lech_goals', 'tips.opponent_goals', 'tips.save_order', 'users.team_name', 'users.name']);
        if ($tips->isEmpty()) {
            return null;
        }

        $scored = $tips->map(fn (Tip $t) => ['tip' => $t] + Scoring::tip($t->lech_goals, $t->opponent_goals, $lech, $rival));
        $outcomeHits = $scored->where('outcome', true);
        $percent = (int) round($outcomeHits->count() * 100 / $tips->count());
        if ($outcomeHits->isEmpty() || $percent > self::AGAINST_PERCENT) {
            return null;
        }

        $exact = $scored->where('exact', true);
        $winners = ($exact->isNotEmpty() ? $exact : $outcomeHits)->sortBy(fn ($s) => $s['tip']->save_order);
        $names = $winners->map(fn (array $s) => (string) ($s['tip']->getAttribute('team_name') ?: $s['tip']->getAttribute('name')))->values();

        $diff = $lech <=> $rival;
        $outcome = match ($diff) {
            0 => __('a draw'),
            1 => __('a win for :team', ['team' => config('club.short_name')]),
            default => __('a win for :team', ['team' => $matchday->opponent]),
        };

        return [
            'percent' => $percent,
            'outcome' => (string) $outcome,
            'exact' => $exact->isNotEmpty(),
            'names' => array_values($names->take(self::NAMES)->all()),
            'more' => max(0, $names->count() - self::NAMES),
        ];
    }

    /**
     * Liderzy wyścigu o nagrody indywidualne: nagroda => [etykieta, zespół, wartość z jednostką].
     *
     * @return list<array{label: string, name: string, value: string}>
     */
    public static function awardLeaders(Season $season): array
    {
        $leaders = [];
        foreach (AwardRace::meta() as $award => $meta) {
            $first = AwardRace::ranking($season, $award)[0] ?? null;
            if ($first && (int) $first['value'] > 0) {
                $leaders[] = ['label' => $meta['label'], 'name' => (string) $first['name'], 'value' => $first['value'].' '.$meta['unit']];
            }
        }

        return $leaders;
    }
}
