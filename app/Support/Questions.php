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
 * Pytania w kolejce (zasady przebudowy 2.0, decyzje MolaQ z 9.11.2026):
 *  - w kolejce jest najwyżej PER_MATCHDAY pytań tak/nie, układa je admin z banku (question_templates),
 *  - gracz odpowiada osobno w każdym typie rozgrywek (liga, Puchar Polski, puchary europejskie, Liga Legend; decyzja z 13.11.2026),
 *  - w każdym typie może pominąć dowolne pytanie; każde, na które odpowiada, oznacza jako atak albo obrona,
 *  - trafione pytanie ataku = +1 do ataku, trafione pytanie obrony = +1 do obrony,
 *  - jedno pudło zeruje oba bonusy z pytań w tym typie rozgrywek (zawodnicy liczą się dalej),
 *  - pytanie unieważnione (voided) nie liczy się nikomu i niczego nie zeruje.
 */
final class Questions
{
    public const PER_MATCHDAY = 5;

    /** @return Collection<int, MatchdayQuestion> */
    public static function for(Matchday $matchday): Collection
    {
        return MatchdayQuestion::where('matchday_id', $matchday->id)->orderBy('position')->get();
    }

    /**
     * Typy rozgrywek z osobnymi odpowiedziami (wartości score_set).
     *
     * @return list<string>
     */
    public static function sets(): array
    {
        return [CompetitionType::League->value, CompetitionType::Cup->value, CompetitionType::Champions->value, CompetitionType::Legends->value];
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
     * Krótkie podsumowanie odpowiedzi gracza w jednym typie rozgrywek.
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
     * Gracz odpowiada (true/false) albo pomija pytanie (null) w jednym typie rozgrywek. Strona bonusu zostaje, jeśli już była.
     *
     * @throws DomainException
     */
    public static function answer(User $user, MatchdayQuestion $question, string $set, ?bool $answer): void
    {
        self::ensureOpen($question);
        self::ensureSet($set);

        if ($answer === null) {
            QuestionAnswer::where('matchday_question_id', $question->id)->where('user_id', $user->id)->where('score_set', $set)->delete();

            return;
        }

        QuestionAnswer::updateOrCreate(
            ['matchday_question_id' => $question->id, 'user_id' => $user->id, 'score_set' => $set],
            ['answer' => $answer],
        );
    }

    /**
     * Strona bonusu pytania w typie rozgrywek: attack albo defense. Działa tylko dla pytania, na które gracz odpowiedział.
     *
     * @throws DomainException
     */
    public static function side(User $user, MatchdayQuestion $question, string $set, string $side): void
    {
        self::ensureOpen($question);
        self::ensureSet($set);

        if (! in_array($side, QuestionAnswer::SIDES, true)) {
            throw new DomainException(__('Unknown bonus side.'));
        }

        QuestionAnswer::where('matchday_question_id', $question->id)->where('user_id', $user->id)->where('score_set', $set)->update(['side' => $side]);
    }

    /**
     * Kopiuje odpowiedzi i strony z jednego typu rozgrywek do innych (pominięte pytania też: tam znikają).
     *
     * @param  list<string>  $targets
     *
     * @throws DomainException
     */
    public static function copy(User $user, Matchday $matchday, string $from, array $targets): void
    {
        if (! $matchday->isOpenForTips()) {
            throw new DomainException(__('Tipping for this matchday is closed.'));
        }
        self::ensureSet($from);

        $questionIds = MatchdayQuestion::where('matchday_id', $matchday->id)->pluck('id');
        $source = QuestionAnswer::where('user_id', $user->id)->where('score_set', $from)->whereIn('matchday_question_id', $questionIds)->get();

        DB::transaction(function () use ($user, $questionIds, $source, $from, $targets) {
            foreach (array_diff($targets, [$from]) as $set) {
                self::ensureSet($set);
                QuestionAnswer::where('user_id', $user->id)->where('score_set', $set)->whereIn('matchday_question_id', $questionIds)->delete();

                foreach ($source as $answer) {
                    QuestionAnswer::create([
                        'matchday_question_id' => $answer->matchday_question_id,
                        'user_id' => $user->id,
                        'score_set' => $set,
                        'answer' => $answer->answer,
                        'side' => $answer->side,
                    ]);
                }
            }
        });
    }

    /**
     * Bonus z pytań: trafione ataku i obrony, a przy choćby jednym pudle zero i zero.
     * Pytania bez wpisanej odpowiedzi admina na razie się nie liczą.
     *
     * @param  Collection<int, MatchdayQuestion>  $questions
     * @param  Collection<int, QuestionAnswer>  $answers  id pytania => odpowiedź gracza
     * @return array{attack: int, defense: int, missed: bool}
     */
    public static function bonus(Collection $questions, Collection $answers): array
    {
        $attack = 0;
        $defense = 0;

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);

            if ($answer === null || $question->voided || $question->correct === null) {
                continue;
            }

            if ($answer->answer !== $question->correct) {
                return ['attack' => 0, 'defense' => 0, 'missed' => true];
            }

            $answer->side === 'defense' ? $defense++ : $attack++;
        }

        return ['attack' => $attack, 'defense' => $defense, 'missed' => false];
    }

    /**
     * Bonusy z pytań wszystkich graczy kolejki naraz (rozliczenie): user_id => typ rozgrywek => bonus.
     *
     * @return array<int, array<string, array{attack: int, defense: int, missed: bool}>>
     */
    public static function bonusesFor(Matchday $matchday): array
    {
        $questions = self::for($matchday);

        if ($questions->isEmpty()) {
            return [];
        }

        /** @var array<int, array<string, array{attack: int, defense: int, missed: bool}>> $out */
        $out = [];
        foreach (QuestionAnswer::whereIn('matchday_question_id', $questions->pluck('id'))->get()->groupBy(['user_id', 'score_set']) as $userId => $bySet) {
            foreach ($bySet as $set => $answers) {
                $out[(int) $userId][(string) $set] = self::bonus($questions, $answers->keyBy('matchday_question_id'));
            }
        }

        return $out;
    }

    /**
     * Admin układa pytania kolejki z banku (lista id szablonów w kolejności, najwyżej PER_MATCHDAY).
     * Pytania, na które ktoś już odpowiedział, zostają, jeśli są dalej na liście.
     *
     * @param  list<int>  $templateIds
     */
    public static function setForMatchday(Matchday $matchday, array $templateIds): void
    {
        $templateIds = array_slice(array_values(array_unique($templateIds)), 0, self::PER_MATCHDAY);
        $templates = QuestionTemplate::whereIn('id', $templateIds)->get()->keyBy('id');

        DB::transaction(function () use ($matchday, $templateIds, $templates) {
            $existing = MatchdayQuestion::where('matchday_id', $matchday->id)->get()->keyBy('question_template_id');

            // Usuwamy pytania spoza nowej listy, a pozostałe chwilowo przesuwamy, żeby nie zderzyć się z unikalną pozycją.
            MatchdayQuestion::where('matchday_id', $matchday->id)->whereNotIn('question_template_id', $templateIds)->delete();
            MatchdayQuestion::where('matchday_id', $matchday->id)->update(['position' => DB::raw('position + 100')]);

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
                        'question_template_id' => $template->id,
                        'position' => $index + 1,
                        'text' => $template->text,
                    ]);
                    $template->increment('times_used');
                }
            }
        });
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
