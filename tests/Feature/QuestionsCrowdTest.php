<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Models\Season;
use App\Models\User;
use App\Support\Questions;

// Jak odpowiadali kibice: odsetek TAK przy pytaniu i liczba odpowiedzi (pasek pod pytaniem po zamknięciu typowania).

test('the crowd shows the share of yes answers for each question', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia Warszawa', 'is_home' => true,
        'kickoff_at' => now()->subDay(), 'status' => MatchdayStatus::Played->value]);
    $asked = MatchdayQuestion::create(['matchday_id' => $matchday->id, 'score_set' => 'league', 'position' => 1, 'text' => 'Czy Lech strzeli gola?']);
    $silent = MatchdayQuestion::create(['matchday_id' => $matchday->id, 'score_set' => 'league', 'position' => 2, 'text' => 'Czy będzie karny?']);
    foreach ([true, true, false] as $answer) {
        QuestionAnswer::create(['matchday_question_id' => $asked->id, 'user_id' => User::factory()->create()->id, 'score_set' => 'league', 'answer' => $answer]);
    }

    expect(Questions::crowd([$asked->id, $silent->id]))->toBe([
        $asked->id => ['yes' => 67, 'answers' => 3],
        $silent->id => ['yes' => null, 'answers' => 0],
    ])->and(Questions::crowd([]))->toBe([]);
});
