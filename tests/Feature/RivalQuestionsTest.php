<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Models\Season;
use App\Models\User;
use App\Support\Rivals;

// Pytania rywala w podglądzie pojedynku: wszyscy widzą, na ile pytań odpowiedział, premium także ile ataków i obron.

function rqSetup(): array
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create([
        'season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia', 'is_home' => true,
        'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value,
    ]);
    $rival = User::factory()->create();
    $questions = collect(range(1, 4))->map(fn ($n) => MatchdayQuestion::create(['matchday_id' => $matchday->id, 'score_set' => 'league', 'position' => $n, 'text' => 'Pytanie '.$n]));
    foreach ([[0, 'attack'], [1, 'defense'], [2, 'defense']] as [$q, $side]) {
        QuestionAnswer::create(['matchday_question_id' => $questions[$q]->id, 'user_id' => $rival->id, 'score_set' => 'league', 'answer' => true, 'side' => $side]);
    }

    return [$matchday, $rival];
}

function rqQuestions(...$args): ?array
{
    return (fn (...$a) => self::questions(...$a))->call(new Rivals, ...$args);
}

test('without premium only the number of answered questions is shown', function () {
    [$matchday, $rival] = rqSetup();

    expect(rqQuestions($rival->id, $matchday, 'league', Rivals::OPEN, false))
        ->toBe(['total' => 4, 'answered' => 3, 'attack' => null, 'defense' => null]);
});

test('premium sees how many attacks and defences the rival picked', function () {
    [$matchday, $rival] = rqSetup();

    expect(rqQuestions($rival->id, $matchday, 'league', Rivals::OPEN, true))
        ->toBe(['total' => 4, 'answered' => 3, 'attack' => 1, 'defense' => 2]);
});

test('a set without questions and a bot show nothing', function () {
    [$matchday, $rival] = rqSetup();

    expect(rqQuestions($rival->id, $matchday, 'cup', Rivals::OPEN, true))->toBeNull()
        ->and(rqQuestions(null, $matchday, 'league', Rivals::OPEN, true))->toBeNull();
});

test('the icons hide the side without premium and sort swords before shields with premium', function () {
    $plain = (string) $this->blade('<x-question-sides :info="$info" />', ['info' => ['total' => 4, 'answered' => 3, 'attack' => null, 'defense' => null]]);
    $premium = (string) $this->blade('<x-question-sides :info="$info" />', ['info' => ['total' => 4, 'answered' => 3, 'attack' => 1, 'defense' => 2]]);

    expect(substr_count($plain, 'var(--q-attack)') + substr_count($plain, 'var(--q-defense)'))->toBe(0)
        ->and(substr_count($plain, 'bg-club'))->toBe(3)
        ->and(substr_count($premium, 'var(--q-attack)'))->toBe(1)
        ->and(substr_count($premium, 'var(--q-defense)'))->toBe(2)
        ->and(strpos($premium, 'var(--q-attack)'))->toBeLessThan(strpos($premium, 'var(--q-defense)'))
        ->and(substr_count($premium, 'border-dashed'))->toBe(1);
});
