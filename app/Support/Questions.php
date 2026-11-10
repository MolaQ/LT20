<?php

namespace App\Support;

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
 *  - gracz może pominąć dowolne pytanie; każde, na które odpowiada, oznacza jako atak albo obrona,
 *  - trafione pytanie ataku = +1 do ataku, trafione pytanie obrony = +1 do obrony,
 *  - jedno pudło zeruje oba bonusy z pytań (zawodnicy liczą się dalej),
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
     * Odpowiedzi gracza w kolejce: id pytania => odpowiedź.
     *
     * @return Collection<int, QuestionAnswer>
     */
    public static function answers(int $userId, Matchday $matchday): Collection
    {
        return QuestionAnswer::where('user_id', $userId)
            ->whereIn('matchday_question_id', MatchdayQuestion::where('matchday_id', $matchday->id)->select('id'))
            ->get()
            ->keyBy('matchday_question_id');
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
            ['matchday_question_id' => $question->id, 'user_id' => $user->id],
            ['answer' => $answer],
        );
    }

    /**
     * Strona bonusu pytania: attack albo defense. Działa tylko dla pytania, na które gracz odpowiedział.
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
     * Bonusy z pytań wszystkich graczy kolejki naraz (rozliczenie): user_id => bonus.
     *
     * @return array<int, array{attack: int, defense: int, missed: bool}>
     */
    public static function bonusesFor(Matchday $matchday): array
    {
        $questions = self::for($matchday);

        if ($questions->isEmpty()) {
            return [];
        }

        $out = [];
        foreach (QuestionAnswer::whereIn('matchday_question_id', $questions->pluck('id'))->get()->groupBy('user_id') as $userId => $answers) {
            $out[(int) $userId] = self::bonus($questions, $answers->keyBy('matchday_question_id'));
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
    private static function ensureOpen(MatchdayQuestion $question): void
    {
        if (! $question->matchday?->isOpenForTips()) {
            throw new DomainException(__('Tipping for this matchday is closed.'));
        }
    }
}
