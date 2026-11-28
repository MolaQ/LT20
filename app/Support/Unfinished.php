<?php

namespace App\Support;

use App\Models\Matchday;
use App\Models\MatchdayQuestion;

/**
 * Niedokończona kolejka gracza (28.11.2026): typ rozgrywek, w którym nie wystawił żadnego zawodnika albo nie
 * odpowiedział na wszystkie pytania. Do przypomnień (push „Masz typ, ale…” i mail przed meczem), żeby nikt nie tracił
 * bramek ataku i obrony tylko przez to, że zapomniał o składzie.
 */
final class Unfinished
{
    /**
     * Braki gracza w kolejce, po typach rozgrywek, w których gra (także w sparingu).
     *
     * @return list<array{set: string, label: string, squad: bool, questions: int}> squad: brak zawodników, questions: ile pytań bez odpowiedzi
     */
    public static function for(int $userId, Matchday $matchday): array
    {
        $types = PlayerCompetitions::types($userId, $matchday->season_id, $matchday->number);
        if ($types === []) {
            return [];
        }

        $picks = Squad::picks($userId, null, $matchday);
        $answers = Questions::answers($userId, $matchday);
        $questions = MatchdayQuestion::where('matchday_id', $matchday->id)->get(['id', 'score_set'])->groupBy('score_set');
        $friendlySet = Friendlies::setInRound($userId, $matchday->season_id, $matchday->number);
        $friendly = $friendlySet ? Friendlies::acceptedFor($userId, $matchday) : null;

        $out = [];
        foreach ($types as $type) {
            $set = $type->scoreSet()->value;
            $asked = $questions->get($set)?->count() ?? 0;
            $answered = $answers->get($set)?->count() ?? 0;
            $missingSquad = ($picks[$set] ?? []) === [];
            $missingQuestions = max(0, $asked - $answered);

            if ($missingSquad || $missingQuestions > 0) {
                $out[] = [
                    'set' => $set,
                    'label' => $set === $friendlySet && $friendly ? Friendlies::label($friendly, $userId) : $type->scoreSetLabel(),
                    'squad' => $missingSquad,
                    'questions' => $missingQuestions,
                ];
            }
        }

        return $out;
    }

    /**
     * Braki jednym zdaniem do powiadomienia: „Puchar Polski: bez składu, 2 pytania bez odpowiedzi; …”.
     *
     * @param  list<array{set: string, label: string, squad: bool, questions: int}>  $missing
     */
    public static function sentence(array $missing): string
    {
        return implode('; ', array_map(function (array $row) {
            $parts = [];
            if ($row['squad']) {
                $parts[] = __('no squad');
            }
            if ($row['questions'] > 0) {
                $parts[] = trans_choice(':count question unanswered|:count questions unanswered', $row['questions']);
            }

            return $row['label'].': '.implode(', ', $parts);
        }, $missing));
    }
}
