<?php

namespace App\Support;

use App\Enums\CompetitionType;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Models\QuestionTemplate;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pytania w kolejce (zasady przebudowy 2.0, decyzje MolaQ z 9.11.2026 i 13.11.2026):
 *  - każdy typ rozgrywek (liga, Puchar Polski, puchary europejskie, Liga Legend) ma w kolejce osobny zestaw
 *    najwyżej PER_SET pytań tak/nie; admin układa je z banku (question_templates), a jedno pytanie z banku
 *    trafia w kolejce najwyżej do jednego zestawu,
 *  - gracz odpowiada na pytania zestawów rozgrywek, w których jeszcze gra; może pominąć dowolne pytanie,
 *    a każde, na które odpowiada, oznacza jako atak (OF) albo obrona (DEF),
 *  - trafione pytanie ataku = +1 do ataku, trafione pytanie obrony = +1 do obrony w tych rozgrywkach,
 *  - pudło zeruje bonus tylko swojej strony w tych rozgrywkach: pudło w pytaniu ataku zeruje atak z pytań,
 *    pudło w pytaniu obrony zeruje obronę z pytań (decyzja MolaQ z 19.11.2026; zawodnicy liczą się dalej),
 *  - pytanie unieważnione (voided) nie liczy się nikomu i niczego nie zeruje.
 */
final class Questions
{
    /** Najwięcej pytań w zestawie jednego typu rozgrywek. */
    public const PER_SET = 5;

    /**
     * Pytania kolejki: wszystkie albo jednego zestawu.
     *
     * @return Collection<int, MatchdayQuestion>
     */
    public static function for(Matchday $matchday, ?string $set = null): Collection
    {
        return MatchdayQuestion::where('matchday_id', $matchday->id)
            ->when($set !== null, fn ($q) => $q->where('score_set', $set))
            ->orderBy('position')->get();
    }

    /**
     * Typy rozgrywek z osobnymi zestawami pytań (wartości score_set).
     *
     * @return list<string>
     */
    public static function sets(): array
    {
        return [CompetitionType::League->value, CompetitionType::Cup->value, CompetitionType::Champions->value, CompetitionType::Legends->value];
    }

    /** Nazwa zestawu, np. „Ligi i Liga podwórkowa”. */
    public static function setLabel(string $set): string
    {
        return CompetitionType::from($set)->scoreSetLabel();
    }

    /**
     * Odpowiedzi gracza w kolejce: typ rozgrywek => (id pytania => odpowiedź).
     *
     * @return Collection<string, Collection<int, QuestionAnswer>>
     */
    public static function answers(int $userId, Matchday $matchday): Collection
    {
        /** @var Collection<string, Collection<int, QuestionAnswer>> $grouped */
        $grouped = QuestionAnswer::where('user_id', $userId)
            ->whereIn('matchday_question_id', MatchdayQuestion::where('matchday_id', $matchday->id)->select('id'))
            ->get()
            ->groupBy('score_set')
            ->map(fn (Collection $answers) => $answers->keyBy('matchday_question_id'));

        return $grouped;
    }

    /**
     * Jak odpowiadali kibice: id pytania => odsetek odpowiedzi TAK (null, gdy nikt nie odpowiedział) i liczba odpowiedzi.
     *
     * @param  iterable<int, int>  $questionIds
     * @return array<int, array{yes: ?int, answers: int}>
     */
    public static function crowd(iterable $questionIds): array
    {
        $ids = collect($questionIds)->values();
        $counts = $ids->isEmpty() ? collect() : QuestionAnswer::whereIn('matchday_question_id', $ids)
            ->selectRaw('matchday_question_id, COUNT(*) as total, SUM(CASE WHEN answer THEN 1 ELSE 0 END) as yes')
            ->groupBy('matchday_question_id')
            ->toBase()
            ->get()
            ->keyBy('matchday_question_id');

        $crowd = [];
        foreach ($ids as $id) {
            $row = $counts->get($id);
            $total = (int) ($row->total ?? 0);
            $crowd[(int) $id] = ['yes' => $total > 0 ? (int) round((int) $row->yes * 100 / $total) : null, 'answers' => $total];
        }

        return $crowd;
    }

    /**
     * Krótkie podsumowanie odpowiedzi gracza w jednym zestawie.
     *
     * @param  Collection<int, QuestionAnswer>  $answers
     * @return array{answered: int, attack: int, defense: int}
     */
    public static function summary(Collection $answers): array
    {
        return [
            'answered' => $answers->count(),
            'attack' => $answers->where('side', 'attack')->count(),
            'defense' => $answers->where('side', 'defense')->count(),
        ];
    }

    /**
     * Gracz odpowiada (true/false) albo pomija pytanie (null). Strona bonusu zostaje, jeśli już była.
     *
     * @throws DomainException
     */
    public static function answer(User $user, MatchdayQuestion $question, ?bool $answer): void
    {
        self::ensureOpen($question);

        if ($answer === null) {
            QuestionAnswer::where('matchday_question_id', $question->id)->where('user_id', $user->id)->delete();

            return;
        }

        QuestionAnswer::updateOrCreate(
            ['matchday_question_id' => $question->id, 'user_id' => $user->id, 'score_set' => $question->score_set],
            ['answer' => $answer],
        );
    }

    /**
     * Strona bonusu pytania: attack (OF) albo defense (DEF). Działa tylko dla pytania, na które gracz odpowiedział.
     *
     * @throws DomainException
     */
    public static function side(User $user, MatchdayQuestion $question, string $side): void
    {
        self::ensureOpen($question);

        if (! in_array($side, QuestionAnswer::SIDES, true)) {
            throw new DomainException(__('Unknown bonus side.'));
        }

        QuestionAnswer::where('matchday_question_id', $question->id)->where('user_id', $user->id)->update(['side' => $side]);
    }

    /**
     * Bonus z pytań osobno dla ataku i obrony: trafienia danej strony, a przy choćby jednym pudle tej strony zero.
     * Pudło w pytaniu ataku nie rusza obrony i odwrotnie. Pytania bez wpisanej odpowiedzi admina na razie się nie liczą.
     *
     * @param  Collection<int, MatchdayQuestion>  $questions
     * @param  Collection<int, QuestionAnswer>  $answers  id pytania => odpowiedź gracza
     * @return array{attack: int, defense: int, attack_missed: bool, defense_missed: bool}
     */
    public static function bonus(Collection $questions, Collection $answers): array
    {
        $hits = ['attack' => 0, 'defense' => 0];
        $missed = ['attack' => false, 'defense' => false];

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);

            if ($answer === null || $question->voided || $question->correct === null) {
                continue;
            }

            $side = $answer->side === 'defense' ? 'defense' : 'attack';

            if ($answer->answer !== $question->correct) {
                $missed[$side] = true;
            } else {
                $hits[$side]++;
            }
        }

        return [
            'attack' => $missed['attack'] ? 0 : $hits['attack'],
            'defense' => $missed['defense'] ? 0 : $hits['defense'],
            'attack_missed' => $missed['attack'],
            'defense_missed' => $missed['defense'],
        ];
    }

    /**
     * Bonusy z pytań wszystkich graczy kolejki naraz (rozliczenie): user_id => typ rozgrywek => bonus z jego zestawu.
     *
     * @return array<int, array<int|string, array{attack: int, defense: int, attack_missed: bool, defense_missed: bool}>>
     */
    public static function bonusesFor(Matchday $matchday): array
    {
        $questions = self::for($matchday);

        if ($questions->isEmpty()) {
            return [];
        }

        $bySet = $questions->groupBy('score_set');

        /** @var array<int, array<int|string, array{attack: int, defense: int, attack_missed: bool, defense_missed: bool}>> $out */
        $out = [];
        foreach (QuestionAnswer::whereIn('matchday_question_id', $questions->pluck('id'))->get()->groupBy(['user_id', 'score_set']) as $userId => $answersBySet) {
            foreach ($answersBySet as $set => $answers) {
                $out[(int) $userId][(string) $set] = self::bonus($bySet->get($set, collect()), $answers->keyBy('matchday_question_id'));
            }
        }

        return $out;
    }

    /**
     * Admin układa zestaw pytań jednego typu rozgrywek z banku (lista id szablonów w kolejności, najwyżej PER_SET).
     * Pytania z banku użyte już w innym zestawie tej kolejki są pomijane. Pytania, na które ktoś już odpowiedział,
     * zostają, jeśli są dalej na liście.
     *
     * @param  list<int>  $templateIds
     *
     * @throws DomainException
     */
    public static function setForMatchday(Matchday $matchday, string $set, array $templateIds): void
    {
        self::ensureSet($set);

        $usedElsewhere = MatchdayQuestion::where('matchday_id', $matchday->id)->where('score_set', '!=', $set)
            ->whereNotNull('question_template_id')->pluck('question_template_id')->map(fn ($id) => (int) $id)->all();
        $templateIds = array_values(array_diff(array_unique($templateIds), $usedElsewhere));
        $templateIds = array_slice($templateIds, 0, self::PER_SET);
        $templates = QuestionTemplate::whereIn('id', $templateIds)->get()->keyBy('id');

        DB::transaction(function () use ($matchday, $set, $templateIds, $templates) {
            $inSet = fn () => MatchdayQuestion::where('matchday_id', $matchday->id)->where('score_set', $set);
            $existing = $inSet()->get()->keyBy('question_template_id');

            // Usuwamy pytania spoza nowej listy, a pozostałe chwilowo przesuwamy, żeby nie zderzyć się z unikalną pozycją.
            $inSet()->whereNotIn('question_template_id', $templateIds)->delete();
            $inSet()->update(['position' => DB::raw('position + 100')]);

            foreach ($templateIds as $index => $templateId) {
                $template = $templates->get($templateId);
                if (! $template) {
                    continue;
                }

                if ($question = $existing->get($templateId)) {
                    $question->update(['position' => $index + 1]);
                } else {
                    MatchdayQuestion::create([
                        'matchday_id' => $matchday->id,
                        'score_set' => $set,
                        'question_template_id' => $template->id,
                        'position' => $index + 1,
                        'text' => $template->text,
                    ]);
                    $template->increment('times_used');
                }
            }
        });
    }

    /**
     * Automat: uzupełnia zestaw kolejki do PER_SET pytań z aktywnego banku. Z jednej kategorii bierze najwyżej jedno pytanie
     * (licząc też te, które już są w zestawie), pomija pytania z innych zestawów tej kolejki, a najpierw sięga po najrzadziej
     * używane (przy remisie losowo). Pytania bez kategorii nie blokują się nawzajem. Zwraca liczbę dodanych pytań.
     */
    public static function autoFill(Matchday $matchday, string $set): int
    {
        self::ensureSet($set);

        $all = self::for($matchday);
        $current = $all->where('score_set', $set)->pluck('question_template_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $taken = $all->pluck('question_template_id')->filter()->map(fn ($id) => (int) $id)->all();
        $free = self::PER_SET - count($current);

        if ($free <= 0) {
            return 0;
        }

        $usedCategories = QuestionTemplate::whereIn('id', $current)->whereNotNull('category')->pluck('category')
            ->map(fn ($c) => mb_strtolower($c))->all();

        $picked = [];
        foreach (QuestionTemplate::where('is_active', true)->whereNotIn('id', $taken)->get()
            ->shuffle()->sortBy('times_used') as $template) {
            $category = $template->category ? mb_strtolower($template->category) : null;
            if ($category !== null && in_array($category, $usedCategories, true)) {
                continue;
            }

            $picked[] = $template->id;
            if ($category !== null) {
                $usedCategories[] = $category;
            }
            if (count($picked) === $free) {
                break;
            }
        }

        if ($picked !== []) {
            self::setForMatchday($matchday, $set, [...$current, ...$picked]);
        }

        return count($picked);
    }

    /** @throws DomainException */
    private static function ensureSet(string $set): void
    {
        if (! in_array($set, self::sets(), true)) {
            throw new DomainException(__('Unknown competition type.'));
        }
    }

    /** @throws DomainException */
    private static function ensureOpen(MatchdayQuestion $question): void
    {
        if (! $question->matchday?->isOpenForTips()) {
            throw new DomainException(__('Tipping for this matchday is closed.'));
        }
    }
}
