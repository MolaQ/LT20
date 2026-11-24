<?php

use App\Enums\MatchCompetition;
use App\Enums\SeasonStatus;
use App\Models\LechMatch;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Tip;
use App\Models\User;

// Szkic kolejki (24.11.2026): nowy mecz w pustej kolejce czeka jako szkic, aż admin go opublikuje.

function draftMatch(string $opponent, string $kickoff): LechMatch
{
    return LechMatch::create(['opponent' => $opponent, 'is_home' => true, 'competition' => MatchCompetition::DEFAULT->value, 'kickoff_at' => $kickoff, 'postponed' => false]);
}

test('a new match makes the matchday a draft that is closed for tips until published', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create(['season_id' => $season->id, 'number' => 1]);

    $matchday->assignMatch(draftMatch('Legia Warszawa', now()->addDays(3)->toDateTimeString()));

    expect($matchday->is_draft)->toBeTrue()
        ->and($matchday->isUpcoming())->toBeTrue()
        ->and($matchday->isOpenForTips())->toBeFalse()
        ->and($matchday->isReady())->toBeFalse();

    $matchday->publish();

    expect($matchday->fresh()->is_draft)->toBeFalse()
        ->and($matchday->fresh()->isOpenForTips())->toBeTrue();

    // Nowy termin w opublikowanej kolejce nie robi z niej znowu szkicu.
    $matchday->assignMatch(draftMatch('Legia Warszawa', now()->addDays(4)->toDateTimeString()));

    expect($matchday->fresh()->is_draft)->toBeFalse();
});

test('a matchday that already has tips never becomes a draft', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create(['season_id' => $season->id, 'number' => 1]);
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => User::factory()->create()->id, 'lech_goals' => 1, 'opponent_goals' => 0, 'saved_at' => now()]);

    $matchday->assignMatch(draftMatch('Wisła Kraków', now()->addDays(3)->toDateTimeString()));

    expect($matchday->is_draft)->toBeFalse();
});
