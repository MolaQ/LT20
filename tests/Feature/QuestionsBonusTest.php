<?php

use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Support\Questions;

// Bonus z pytań: trafione ataku i obrony, jedno pudło zeruje oba, pytania unieważnione i bez odpowiedzi admina się nie liczą.

function qbQuestion(int $id, ?bool $correct, bool $voided = false): MatchdayQuestion
{
    $q = new MatchdayQuestion(['correct' => $correct, 'voided' => $voided]);
    $q->id = $id;

    return $q;
}

function qbAnswer(int $questionId, bool $value, string $side): QuestionAnswer
{
    return new QuestionAnswer(['matchday_question_id' => $questionId, 'answer' => $value, 'side' => $side]);
}

test('hits count towards the chosen side', function () {
    $questions = collect([qbQuestion(1, true), qbQuestion(2, false), qbQuestion(3, true)]);
    $answers = collect([1 => qbAnswer(1, true, 'attack'), 2 => qbAnswer(2, false, 'defense'), 3 => qbAnswer(3, true, 'attack')]);

    expect(Questions::bonus($questions, $answers))->toBe(['attack' => 2, 'defense' => 1, 'missed' => false]);
});

test('one miss wipes both bonuses', function () {
    $questions = collect([qbQuestion(1, true), qbQuestion(2, false)]);
    $answers = collect([1 => qbAnswer(1, true, 'attack'), 2 => qbAnswer(2, true, 'defense')]);

    expect(Questions::bonus($questions, $answers))->toBe(['attack' => 0, 'defense' => 0, 'missed' => true]);
});

test('skipped, voided and unsettled questions do not count', function () {
    $questions = collect([qbQuestion(1, true), qbQuestion(2, false, voided: true), qbQuestion(3, null), qbQuestion(4, true)]);
    $answers = collect([2 => qbAnswer(2, true, 'attack'), 3 => qbAnswer(3, false, 'attack'), 4 => qbAnswer(4, true, 'defense')]);

    expect(Questions::bonus($questions, $answers))->toBe(['attack' => 0, 'defense' => 1, 'missed' => false]);
});
