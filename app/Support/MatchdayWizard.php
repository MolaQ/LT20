<?php

namespace App\Support;

use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use Illuminate\Support\Collection;

/**
 * Kreator kolejki w panelu admina: kroki od rywala do przeliczenia z ich stanem.
 * Stan kroku: done (zrobione), current (teraz to), todo (później), optional (można pominąć, np. brak pytań).
 */
final class MatchdayWizard
{
    /**
     * @param  Collection<int, MatchdayQuestion>  $questions  pytania kolejki
     * @return list<array{key: string, state: string, note: string}>
     */
    public static function steps(Matchday $matchday, Collection $questions, int $tips): array
    {
        $played = $matchday->status === MatchdayStatus::Played;
        $kickoffPassed = $matchday->kickoff_at !== null && now()->gte($matchday->kickoff_at);
        $hasScore = $matchday->lech_goals !== null && $matchday->opponent_goals !== null;
        $unsettled = $questions->filter(fn (MatchdayQuestion $q) => ! $q->voided && $q->correct === null)->count();

        $raw = [
            'match' => [$matchday->isFilled(), $matchday->isFilled()
                ? ($matchday->opponent.' · '.$matchday->kickoff_at?->format('d.m H:i'))
                : __('Opponent and kick-off missing')],
            'questions' => [$questions->isNotEmpty() ? true : null, __(':count of :max', ['count' => $questions->count(), 'max' => Questions::PER_MATCHDAY])],
            'tips' => [$kickoffPassed || $played, trans_choice(':count tip|:count tips', $tips, ['count' => $tips])],
            'result' => [$hasScore, $hasScore ? $matchday->lech_goals.':'.$matchday->opponent_goals : __('No result yet')],
            'answers' => [$questions->isEmpty() ? null : $unsettled === 0, $questions->isEmpty()
                ? __('No questions')
                : ($unsettled === 0 ? __('All settled') : __(':count to settle', ['count' => $unsettled]))],
            'recalc' => [$played, $played ? __('Points counted') : __('Not counted yet')],
        ];

        $steps = [];
        $currentSet = false;
        foreach ($raw as $key => [$done, $note]) {
            $state = match (true) {
                $done === true => 'done',
                $done === null => 'optional',
                ! $currentSet => 'current',
                default => 'todo',
            };
            $currentSet = $currentSet || $state === 'current';
            $steps[] = ['key' => $key, 'state' => $state, 'note' => $note];
        }

        return $steps;
    }

    /**
     * Kolejka do pokazania na start: pierwsza nierozliczona, a gdy wszystkie są rozliczone, ostatnia.
     *
     * @param  Collection<int, Matchday>  $matchdays
     */
    public static function pick(Collection $matchdays): ?Matchday
    {
        return $matchdays->first(fn (Matchday $m) => $m->status !== MatchdayStatus::Played) ?? $matchdays->last();
    }
}
