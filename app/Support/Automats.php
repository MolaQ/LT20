<?php

namespace App\Support;

use App\Actions\Tips\SaveTip;
use App\Enums\MatchdayStatus;
use App\Enums\PlayerStatus;
use App\Models\Automat;
use App\Models\DesignatedPlayer;
use App\Models\LechPlayer;
use App\Models\Matchday;
use App\Models\QuestionAnswer;
use App\Models\Tip;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Automaty (decyzje MolaQ z 16.11.2026). Zwykłe konta graczy, które o ustawionej porze przed pierwszym gwizdkiem
 * (domyślnie 60 minut) same grają w kolejce, raz na kolejkę:
 *  - typ: stały wynik albo najczęstszy typ graczy w tej chwili; remis w głosowaniu wygrywa wynik lepszy dla Lecha,
 *    a bez typów graczy automat typuje 1:0,
 *  - skład: kadra Lecha bez niedostępnych i odpoczywających, od największego doświadczenia z raportów meczów
 *    (przy równym: grający regularnie, rotacja, rezerwa, potem pozycja i numer); po max_players w każdym typie
 *    rozgrywek, w którym gra, a zawodnik trafia tylko do jednego typu,
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
            self::attempt($automat, 'squad', fn () => self::pickSquad($user, $matchday));
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
     * Skład automatu: ten sam porządek kadry dla każdego typu rozgrywek, kolejne max_players zawodników do kolejnego typu.
     *
     * @return array<string, array<int, int>> typ rozgrywek => id zawodników
     */
    public static function pickSquad(User $user, Matchday $matchday): array
    {
        $sets = PlayerCompetitions::types($user->id, $matchday->season_id, $matchday->number);

        if ($sets === []) {
            return [];
        }

        $order = self::squadOrder($user, $matchday);
        $max = max(0, Designation::value('max_players'));
        $picked = [];

        DB::transaction(function () use ($user, $matchday, $sets, $order, $max, &$picked) {
            DesignatedPlayer::where('matchday_id', $matchday->id)->where('user_id', $user->id)->delete();

            foreach ($sets as $set) {
                foreach (array_splice($order, 0, $max) as $playerId) {
                    DesignatedPlayer::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'score_set' => $set->value, 'lech_player_id' => $playerId]);
                    $picked[$set->value][] = $playerId;
                }
            }
        });

        return $picked;
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
