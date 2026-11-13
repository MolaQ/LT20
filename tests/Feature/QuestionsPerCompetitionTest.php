<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionTemplate;
use App\Models\Season;
use App\Models\User;
use App\Support\Questions;

// Osobny zestaw pytań w każdym typie rozgrywek: odpowiedzi, strony i bonus liczą się dla każdego zestawu oddzielnie,
// a jedno pytanie z banku trafia w kolejce najwyżej do jednego zestawu.

function pqMatchday(): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => now()->addDay(),
        'status' => MatchdayStatus::Planned->value,
    ]);
}

function pqQuestion(Matchday $matchday, string $set, int $position): MatchdayQuestion
{
    return MatchdayQuestion::create(['matchday_id' => $matchday->id, 'score_set' => $set, 'position' => $position, 'text' => 'Pytanie '.$set.' '.$position]);
}

test('each competition has its own questions, answers and bonus', function () {
    $matchday = pqMatchday();
    $league = pqQuestion($matchday, 'league', 1);
    $cupA = pqQuestion($matchday, 'cup', 1);
    $cupB = pqQuestion($matchday, 'cup', 2);
    $user = User::factory()->create();

    Questions::answer($user, $league, true);
    Questions::side($user, $league, 'defense');
    Questions::answer($user, $cupA, false);
    Questions::answer($user, $cupB, true);

    expect(Questions::for($matchday, 'cup')->pluck('id')->all())->toBe([$cupA->id, $cupB->id]);

    $answers = Questions::answers($user->id, $matchday);
    expect($answers->get('league'))->toHaveCount(1)
        ->and($answers->get('cup'))->toHaveCount(2)
        ->and($answers->get('legends'))->toBeNull();

    $league->update(['correct' => true]);
    $cupA->update(['correct' => true]);

    $bonuses = Questions::bonusesFor($matchday->fresh());
    expect($bonuses[$user->id]['league'])->toBe(['attack' => 0, 'defense' => 1, 'missed' => false])
        ->and($bonuses[$user->id]['cup'])->toBe(['attack' => 0, 'defense' => 0, 'missed' => true]);
});

test('skipping a question removes the answer', function () {
    $matchday = pqMatchday();
    $question = pqQuestion($matchday, 'league', 1);
    $user = User::factory()->create();

    Questions::answer($user, $question, true);
    Questions::answer($user, $question, null);

    expect(Questions::answers($user->id, $matchday))->toBeEmpty();
});

test('a bank question goes to only one set of a matchday and a set has at most five', function () {
    $matchday = pqMatchday();
    $templates = collect(range(1, 7))->map(fn (int $i) => QuestionTemplate::create(['text' => 'Pytanie z banku '.$i]));
    $ids = $templates->pluck('id')->all();

    Questions::setForMatchday($matchday, 'league', [$ids[0], $ids[1]]);
    Questions::setForMatchday($matchday, 'cup', $ids);

    expect(Questions::for($matchday, 'league')->pluck('question_template_id')->all())->toBe([$ids[0], $ids[1]])
        ->and(Questions::for($matchday, 'cup')->pluck('question_template_id')->all())->toBe(array_slice($ids, 2, Questions::PER_SET));
});

test('an unknown set is refused', function () {
    Questions::setForMatchday(pqMatchday(), 'swiss', []);
})->throws(DomainException::class);
