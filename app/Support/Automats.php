<?php

namespace App\Support;

use App\Actions\Tips\SaveTip;
use App\Enums\CompetitionType;
use App\Enums\MatchdayStatus;
use App\Enums\PlayerStatus;
use App\Models\Automat;
use App\Models\CompetitionEntry;
use App\Models\DesignatedPlayer;
use App\Models\Fixture;
use App\Models\LechPlayer;
use App\Models\Matchday;
use App\Models\QuestionAnswer;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Automaty (decyzje MolaQ z 16.11.2026). Zwykłe konta graczy, które o ustawionej porze przed pierwszym gwizdkiem
 * (domyślnie 60 minut) same grają w kolejce, raz na kolejkę:
 *  - typ: stały wynik albo najczęstszy typ graczy w tej chwili; remis w głosowaniu wygrywa wynik lepszy dla Lecha,
 *    a bez typów graczy automat typuje 1:0,
 *  - skład: kadra Lecha bez niedostępnych i odpoczywających; strategia osobno w każdym typie rozgrywek i na mecz
 *    z botem (najbardziej doświadczeni z raportów meczów, własna kolejność, losowo albo nikt), a skład poprawiony
 *    w panelu na najbliższą kolejkę ma pierwszeństwo; po max_players w typie, zawodnik tylko w jednym typie,
 *  - pytania: najczęstsze odpowiedzi graczy; dwie z największą zgodnością jako atak (OF), dwie kolejne jako obrona (DEF).
 * Typy, skład i odpowiedzi innych automatów się nie liczą. Zapis typu idzie zwykłą drogą (SaveTip), więc automat
 * pojawia się w „Właśnie typowali” jak każdy gracz.
 */
final class Automats
{
    /** Typ, gdy nikt jeszcze nie typował. */
    public const FALLBACK_TIP = [1, 0];

    /** Ile pytań w zestawie zaznacza jako atak, a ile jako obronę. */
    public const ATTACK_ANSWERS = 2;

    public const DEFENSE_ANSWERS = 2;

    /** Uruchamiane z harmonogramu (automats:run): zwraca, ile razy automaty zagrały. */
    public static function run(): int
    {
        $automats = Automat::with('user.roles')->where('active', true)->get();

        if ($automats->isEmpty()) {
            return 0;
        }

        $matchdays = Matchday::where('status', MatchdayStatus::Planned->value)
            ->whereNotNull('kickoff_at')
            ->where('kickoff_at', '>', now())
            ->where('kickoff_at', '<=', now()->addMinutes((int) $automats->max('minutes_before')))
            ->orderBy('kickoff_at')
            ->get();

        $played = 0;

        foreach ($matchdays as $matchday) {
            foreach ($automats as $automat) {
                $startsAt = $matchday->kickoff_at?->copy()->subMinutes($automat->minutes_before);

                if ($startsAt === null || $automat->last_matchday_id === $matchday->id || now()->lt($startsAt)) {
                    continue;
                }

                self::play($automat, $matchday);
                $played++;
            }
        }

        return $played;
    }

    /** Automat gra w kolejce: typ, skład i pytania. Kolejka zostaje odnotowana, nawet gdy coś się nie udało. */
    public static function play(Automat $automat, Matchday $matchday): void
    {
        $user = $automat->user;

        if ($matchday->isOpenForTips() && $matchday->isFilled() && Players::canPlay($user)) {
            self::attempt($automat, 'tip', fn () => app(SaveTip::class)->handle($user, $matchday, ...self::tipFor($automat, $matchday)));
            self::attempt($automat, 'squad', fn () => self::pickSquad($automat, $matchday));
            self::attempt($automat, 'questions', fn () => self::answerQuestions($user, $matchday));
        }

        $automat->update(['last_matchday_id' => $matchday->id, 'last_run_at' => now()]);
    }

    /**
     * Wynik, który automat typuje.
     *
     * @return array{0: int, 1: int}
     */
    public static function tipFor(Automat $automat, Matchday $matchday): array
    {
        return $automat->tip_mode === Automat::MODE_FIXED
            ? [$automat->tip_lech, $automat->tip_opponent]
            : self::popularTip($matchday);
    }

    /**
     * Najczęstszy typ graczy (bez automatów). Remis w głosowaniu: lepsza różnica dla Lecha, potem więcej goli Lecha.
     *
     * @return array{0: int, 1: int}
     */
    public static function popularTip(Matchday $matchday): array
    {
        $best = Tip::where('matchday_id', $matchday->id)
            ->whereNotIn('user_id', Automat::select('user_id'))
            ->groupBy('lech_goals', 'opponent_goals')
            ->selectRaw('lech_goals, opponent_goals, COUNT(*) as votes')
            ->get()
            ->map(fn (Tip $row) => [(int) $row->getAttribute('votes'), (int) $row->lech_goals - (int) $row->opponent_goals, (int) $row->lech_goals, (int) $row->opponent_goals])
            ->sort(fn (array $a, array $b) => $b <=> $a)
            ->first();

        return $best === null ? self::FALLBACK_TIP : [$best[2], $best[3]];
    }

    /**
     * Skład automatu według planu (App\Support\Automats::plan) zapisany w kolejce.
     *
     * @return array<string, array<int, int>> typ rozgrywek => id zawodników
     */
    public static function pickSquad(Automat $automat, Matchday $matchday): array
    {
        $plan = self::plan($automat, $matchday);
        $userId = $automat->user_id;
        $picked = [];

        DB::transaction(function () use ($plan, $userId, $matchday, &$picked) {
            DesignatedPlayer::where('matchday_id', $matchday->id)->where('user_id', $userId)->delete();

            foreach ($plan as $set => $row) {
                foreach ($row['players'] as $playerId) {
                    DesignatedPlayer::create(['matchday_id' => $matchday->id, 'user_id' => $userId, 'score_set' => $set, 'lech_player_id' => $playerId]);
                    $picked[$set][] = $playerId;
                }
            }
        });

        return $picked;
    }

    /**
     * Plan składu automatu na kolejkę, osobno w każdym typie rozgrywek, w którym gra (podgląd w panelu i zapis przed meczem).
     * Rozgrywki wybierają po kolei (kolejność z panelu), każde swoją strategią i liczbą zawodników; gdy wszyscy rywale
     * w tych rozgrywkach w tej rundzie to boty (terminarz jest znany przed meczem), działa strategia na mecz z botem.
     * Skład poprawiony ręcznie na tę kolejkę ma pierwszeństwo. Zawodnik trafia tylko do jednych rozgrywek, najwyżej
     * max_players w każdych, bez niedostępnych i odpoczywających.
     *
     * @return array<string, array{label: string, opponents: array<int, string>, bot: bool, strategy: string, players: array<int, int>, manual: bool}>
     */
    public static function plan(Automat $automat, Matchday $matchday): array
    {
        $user = $automat->user;
        $sets = PlayerCompetitions::types($user->id, $matchday->season_id, $matchday->number);

        if ($sets === []) {
            return [];
        }

        $free = self::squadOrder($user, $matchday);
        $max = max(0, Designation::value('max_players'));
        $override = ($automat->squad_override['matchday_id'] ?? null) === $matchday->id ? ($automat->squad_override['sets'] ?? []) : null;
        $opponents = self::opponents($user, $matchday);
        $playing = array_map(fn (CompetitionType $set) => $set->value, $sets);
        $used = [];
        $plan = [];

        // Rozgrywki w kolejności z panelu: pierwsze wybierają spośród wszystkich wolnych zawodników.
        foreach (array_values(array_intersect($automat->setOrder(), $playing)) as $value) {
            $rule = $automat->rule($value);
            $rivals = $opponents[$value] ?? [];
            $bot = $rivals !== [] && collect($rivals)->every(fn (array $rival) => $rival['bot']);
            $strategy = $bot && $rule['bot'] !== Automat::SQUAD_SAME ? $rule['bot'] : $rule['strategy'];
            $pool = array_values(array_diff($free, $used));
            $manual = $override !== null && array_key_exists($value, $override);

            $players = $manual
                ? array_slice(array_values(array_intersect(array_map('intval', (array) $override[$value]), $pool)), 0, $max)
                : array_slice(self::orderBy($strategy, $pool, $automat, $matchday), 0, $rule['count']);
            $used = array_merge($used, $players);

            $plan[$value] = [
                'label' => CompetitionType::from($value)->scoreSetLabel(),
                'opponents' => array_column($rivals, 'name'),
                'bot' => $bot,
                'strategy' => $strategy,
                'players' => $players,
                'manual' => $manual,
            ];
        }

        return $plan;
    }

    /**
     * Kolejność zawodników według strategii. $pool jest już od najbardziej doświadczonych.
     *
     * @param  array<int, int>  $pool
     * @return array<int, int>
     */
    public static function orderBy(string $strategy, array $pool, Automat $automat, Matchday $matchday): array
    {
        return match ($strategy) {
            // Własna kolejność, a po niej pozostali według doświadczenia.
            'priority' => array_values(array_unique(array_merge(array_intersect(array_map('intval', $automat->squad_priority ?? []), $pool), $pool))),
            // Najmniej doświadczeni (przy równym doświadczeniu jak w kadrze: grający regularnie, rotacja, rezerwa).
            'least' => self::leastExperienced($pool),
            // Forma (ostatnie 10 meczów Lecha): przy równej formie zostaje kolejność według doświadczenia.
            'form' => self::byForm($pool, true),
            'form_low' => self::byForm($pool, false),
            'random' => $pool === [] ? [] : (new Randomizer(new Mt19937(crc32($matchday->id.'-a'.$automat->id))))->shuffleArray($pool),
            'none' => [],
            default => $pool,
        };
    }

    /**
     * Zawodnicy według formy: od najlepszej ($best) albo od najsłabszej; przy równej formie zostaje kolejność z $pool.
     *
     * @param  array<int, int>  $pool
     * @return array<int, int>
     */
    private static function byForm(array $pool, bool $best): array
    {
        $form = MatchReport::formTotals();
        $rows = array_map(fn (int $id, int $index) => ['id' => $id, 'key' => [($best ? -1 : 1) * ($form[$id] ?? 0.0), $index]], $pool, array_keys($pool));
        usort($rows, fn (array $a, array $b) => $a['key'] <=> $b['key']);

        return array_column($rows, 'id');
    }

    /**
     * Zawodnicy od najmniej doświadczonego; przy równym doświadczeniu zostaje kolejność z $pool.
     *
     * @param  array<int, int>  $pool
     * @return array<int, int>
     */
    private static function leastExperienced(array $pool): array
    {
        $totals = MatchReport::playerTotals();
        $rows = array_map(fn (int $id, int $index) => ['id' => $id, 'key' => [$totals[$id]['experience'] ?? 0.0, $index]], $pool, array_keys($pool));
        usort($rows, fn (array $a, array $b) => $a['key'] <=> $b['key']);

        return array_column($rows, 'id');
    }

    /**
     * Rywale automatu w tej rundzie w każdym typie rozgrywek: nazwa i czy to bot (wolny los w Lidze podwórkowej też).
     *
     * @return array<string, array<int, array{name: string, bot: bool}>>
     */
    public static function opponents(User $user, Matchday $matchday): array
    {
        $team = SeasonTeam::where('season_id', $matchday->season_id)->where('user_id', $user->id)->first();

        if (! $team) {
            return [];
        }

        $entries = CompetitionEntry::with('competition:id,type')->where('season_team_id', $team->id)->get()->keyBy('id');

        if ($entries->isEmpty()) {
            return [];
        }

        $fixtures = Fixture::where('round', $matchday->number)
            ->where(fn ($q) => $q->whereIn('home_entry_id', $entries->keys())->orWhereIn('away_entry_id', $entries->keys()))
            ->get();
        $out = [];

        foreach ($fixtures as $fixture) {
            $homeMine = $fixture->home_entry_id !== null && $entries->has($fixture->home_entry_id);
            $mine = $entries->get($homeMine ? $fixture->home_entry_id : (int) $fixture->away_entry_id);
            $type = $mine?->competition?->getAttribute('type');

            if (! $type instanceof CompetitionType) {
                continue;
            }

            $otherId = $homeMine ? $fixture->away_entry_id : $fixture->home_entry_id;
            $other = $otherId ? CompetitionEntry::with('seasonTeam.user:id,name,team_name', 'seasonTeam.bot:id,name')->find($otherId)?->seasonTeam : null;

            $out[$type->scoreSet()->value][] = $other instanceof SeasonTeam
                ? ['name' => (string) $other->name, 'bot' => $other->bot_id !== null]
                : ['name' => Fixture::VIRTUAL_OPPONENT, 'bot' => true];
        }

        return $out;
    }

    /**
     * Kadra do wyboru bez odpoczywających, od największego doświadczenia z raportów meczów Lecha.
     *
     * @return array<int, int> id zawodników
     */
    public static function squadOrder(User $user, Matchday $matchday): array
    {
        $locks = Squad::locks($user->id, null, $matchday);
        $totals = MatchReport::playerTotals();
        $statusRank = array_flip(array_column(PlayerStatus::cases(), 'value'));

        return Squad::available()
            ->reject(fn (LechPlayer $player) => isset($locks[$player->id]))
            ->values()
            ->map(fn (LechPlayer $player, int $index) => [
                'id' => $player->id,
                'key' => [-($totals[$player->id]['experience'] ?? 0.0), $statusRank[$player->status->value], $index],
            ])
            ->sort(fn (array $a, array $b) => $a['key'] <=> $b['key'])
            ->pluck('id')
            ->all();
    }

    /**
     * Odpowiedzi automatu w każdym zestawie pytań rozgrywek, w których gra.
     *
     * @return array<string, array<int, array{question: int, answer: bool, side: string}>>
     */
    public static function answerQuestions(User $user, Matchday $matchday): array
    {
        $given = [];

        foreach (PlayerCompetitions::types($user->id, $matchday->season_id, $matchday->number) as $set) {
            $questions = Questions::for($matchday, $set->value)->where('voided', false)->keyBy('id');

            if ($questions->isEmpty()) {
                continue;
            }

            $votes = QuestionAnswer::whereIn('matchday_question_id', $questions->keys())
                ->whereNotIn('user_id', Automat::select('user_id'))
                ->groupBy('matchday_question_id', 'answer')
                ->selectRaw('matchday_question_id, answer, COUNT(*) as votes')
                ->get()
                ->groupBy('matchday_question_id');

            // Zgodność graczy: odsetek najczęstszej odpowiedzi, potem liczba odpowiedzi, potem kolejność w zestawie.
            $ranked = $votes->map(function ($rows, $questionId) use ($questions) {
                $yes = (int) $rows->first(fn ($row) => (bool) $row->answer)?->getAttribute('votes');
                $no = (int) $rows->first(fn ($row) => ! $row->answer)?->getAttribute('votes');
                $total = $yes + $no;

                return [
                    'question' => (int) $questionId,
                    'answer' => $yes >= $no,
                    'key' => [-max($yes, $no) / max(1, $total), -$total, $questions[$questionId]->position],
                ];
            })->sort(fn (array $a, array $b) => $a['key'] <=> $b['key'])->values()->take(self::ATTACK_ANSWERS + self::DEFENSE_ANSWERS);

            QuestionAnswer::where('user_id', $user->id)->whereIn('matchday_question_id', $questions->keys())->delete();

            foreach ($ranked as $index => $row) {
                $question = $questions[$row['question']];
                $side = $index < self::ATTACK_ANSWERS ? 'attack' : 'defense';
                Questions::answer($user, $question, $row['answer']);
                Questions::side($user, $question, $side);
                $given[$set->value][] = ['question' => $question->id, 'answer' => $row['answer'], 'side' => $side];
            }
        }

        return $given;
    }

    /** Krok automatu, którego błąd (np. zamknięte typowanie) nie zatrzymuje pozostałych. */
    private static function attempt(Automat $automat, string $step, callable $callback): void
    {
        try {
            $callback();
        } catch (DomainException $e) {
            Log::warning('Automat '.$automat->id.' ('.$step.'): '.$e->getMessage());
        }
    }
}
