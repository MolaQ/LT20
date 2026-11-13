<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\Season;
use App\Models\User;
use App\Support\Questions;

// Pytania osobno w każdym typie rozgrywek: odpowiedzi, strony i bonus liczą się dla każdego typu oddzielnie.

function pqSetup(): array
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => now()->addDay(),
        'status' => MatchdayStatus::Planned->value,
    ]);
    $questions = collect(range(1, 3))->map(fn (int $i) => MatchdayQuestion::create([
        'matchday_id' => $matchday->id, 'position' => $i, 'text' => 'Pytanie '.$i,
    ]));

    return [$matchday, $questions, User::factory()->create()];
}

test('each competition keeps its own answers and bonus', function () {
    [$matchday, $questions, $user] = pqSetup();

    Questions::answer($user, $questions[0], 'league', true);
    Questions::side($user, $questions[0], 'league', 'defense');
    Questions::answer($user, $questions[0], 'cup', false);
    Questions::answer($user, $questions[1], 'cup', true);

    $answers = Questions::answers($user->id, $matchday);
    expect($answers->get('league'))->toHaveCount(1)
        ->and($answers->get('cup'))->toHaveCount(2)
        ->and($answers->get('legends'))->toBeNull();

    $questions[0]->update(['correct' => true]);
    $questions[1]->update(['correct' => true]);

    $bonuses = Questions::bonusesFor($matchday->fresh());
    expect($bonuses[$user->id]['league'])->toBe(['attack' => 0, 'defense' => 1, 'missed' => false])
        ->and($bonuses[$user->id]['cup'])->toBe(['attack' => 0, 'defense' => 0, 'missed' => true]);
});

test('answers can be copied to the other competitions', function () {
    [$matchday, $questions, $user] = pqSetup();

    Questions::answer($user, $questions[0], 'league', true);
    Questions::side($user, $questions[0], 'league', 'defense');
    Questions::answer($user, $questions[2], 'cup', false);

    Questions::copy($user, $matchday, 'league', ['league', 'cup', 'legends']);

    $answers = Questions::answers($user->id, $matchday);
    expect($answers->get('cup')->keys()->all())->toBe([$questions[0]->id])
        ->and($answers->get('cup')->first()->side)->toBe('defense')
        ->and($answers->get('legends'))->toHaveCount(1)
        ->and($answers->get('league'))->toHaveCount(1);
});

test('an unknown competition is refused', function () {
    [, $questions, $user] = pqSetup();

    Questions::answer($user, $questions[0], 'swiss', true);
})->throws(DomainException::class);
