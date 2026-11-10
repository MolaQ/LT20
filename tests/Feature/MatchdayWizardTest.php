<?php

use App\Enums\MatchdayStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Support\MatchdayWizard;

// Kreator kolejki: stan kroków wynika z danych kolejki, pierwszy niezrobiony krok jest bieżący.

function mwStates(Matchday $matchday, array $questions = [], int $tips = 0): array
{
    return collect(MatchdayWizard::steps($matchday, collect($questions), $tips))->pluck('state', 'key')->all();
}

test('an empty matchday starts at the opponent step', function () {
    $states = mwStates(new Matchday(['number' => 1, 'status' => MatchdayStatus::Planned]));

    expect($states)->toBe([
        'match' => 'current', 'questions' => 'optional', 'tips' => 'todo',
        'result' => 'todo', 'answers' => 'optional', 'recalc' => 'todo',
    ]);
});

test('an open matchday with questions waits for tips', function () {
    $matchday = new Matchday(['number' => 2, 'opponent' => 'Legia', 'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned]);
    $states = mwStates($matchday, [new MatchdayQuestion(['correct' => null])], 12);

    expect($states['match'])->toBe('done')
        ->and($states['questions'])->toBe('done')
        ->and($states['tips'])->toBe('current')
        ->and($states['answers'])->toBe('todo');
});

test('a played matchday with settled questions is complete', function () {
    $matchday = new Matchday([
        'number' => 3, 'opponent' => 'Legia', 'kickoff_at' => now()->subDay(),
        'status' => MatchdayStatus::Played, 'lech_goals' => 2, 'opponent_goals' => 1,
    ]);
    $states = mwStates($matchday, [new MatchdayQuestion(['correct' => true]), new MatchdayQuestion(['voided' => true])]);

    expect(array_unique(array_values($states)))->toBe(['done']);
});

test('the wizard opens on the first matchday that is not played', function () {
    $matchdays = collect([
        new Matchday(['number' => 1, 'status' => MatchdayStatus::Played]),
        new Matchday(['number' => 2, 'status' => MatchdayStatus::Planned]),
        new Matchday(['number' => 3, 'status' => MatchdayStatus::Planned]),
    ]);

    expect(MatchdayWizard::pick($matchdays)?->number)->toBe(2)
        ->and(MatchdayWizard::pick(collect([$matchdays[0]]))?->number)->toBe(1);
});
