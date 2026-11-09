<?php

use App\Support\PremiumStats;

// Statystyki premium: forma, serie, odznaki, „co jeśli” i mapa typów liczone z tablic (bez bazy).

function psRow(int $number, int $points, bool $outcome, bool $exact = false, int $bonus = 0, int $questions = 0, bool $tip = true): array
{
    return ['number' => $number, 'has_tip' => $tip, 'points' => $points, 'bonus' => $bonus, 'questions' => $questions, 'outcome' => $outcome, 'diff' => $exact, 'exact' => $exact];
}

test('streaks count correct outcomes in a row', function () {
    $rows = [psRow(1, 1, true), psRow(2, 3, true, true), psRow(3, 0, false), psRow(4, 1, true), psRow(5, 2, true)];

    expect(PremiumStats::streaks($rows))->toBe(['current' => 2, 'best' => 2, 'exact' => 1, 'outcome' => 4, 'played' => 5])
        ->and(PremiumStats::form($rows))->toBe(47); // 7 z 15
});

test('badges are earned from the season rows', function () {
    $rows = [psRow(1, 1, true), psRow(2, 1, true, bonus: 4), psRow(3, 3, true, true, questions: 3)];
    $earned = collect(PremiumStats::badges($rows))->where('earned', true)->pluck('key')->all();

    expect($earned)->toBe(['sniper', 'streak_3', 'ever_present', 'quiz', 'playmaker']);
});

test('what if shows the points for every result', function () {
    $grid = PremiumStats::whatIf([2, 1], [1, 1]);

    expect($grid[2][1])->toBe(['me' => 3, 'rival' => 0])
        ->and($grid[1][1])->toBe(['me' => 0, 'rival' => 3])
        ->and($grid[3][2])->toBe(['me' => 2, 'rival' => 0])
        ->and(PremiumStats::whatIf(null, null)[0][0])->toBe(['me' => null, 'rival' => null]);
});

test('the tip map caps goals at 4+', function () {
    $map = PremiumStats::tipMap([[2, 1], [2, 1], [5, 0], [0, 7]]);

    expect($map['grid'][2][1])->toBe(2)
        ->and($map['grid'][4][0])->toBe(1)
        ->and($map['grid'][0][4])->toBe(1)
        ->and($map['max'])->toBe(2)
        ->and($map['count'])->toBe(4);
});

test('a player without premium sees the premium teaser', function () {
    $this->get(route('my-stats'))->assertRedirect(route('login'));

    $this->actingAs(\App\Models\User::factory()->create())
        ->get(route('my-stats'))
        ->assertOk()
        ->assertSee(__('The joy of numbers'));
});
