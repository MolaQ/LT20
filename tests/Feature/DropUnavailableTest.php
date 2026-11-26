<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\DesignatedPlayer;
use App\Models\LechMatch;
use App\Models\LechPlayer;
use App\Models\MatchAppearance;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\User;
use App\Support\Squad;

// Zawodnik niedostępny albo odpoczywający w chwili gwizdka znika ze składów kolejek przed gwizdkiem, rozegrane zostają.

test('marking a player unavailable removes him from line-ups before kickoff only', function () {
    $user = User::factory()->create();
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $played = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia', 'is_home' => true, 'kickoff_at' => now()->subWeek(), 'status' => MatchdayStatus::Played->value]);
    $next = Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'Raków', 'is_home' => true, 'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value]);
    $hurt = LechPlayer::create(['name' => 'Ishak', 'number' => 9, 'position' => 'forward']);
    $fit = LechPlayer::create(['name' => 'Sousa', 'number' => 10, 'position' => 'midfielder']);

    foreach ([[$played, $hurt], [$next, $hurt], [$next, $fit]] as [$matchday, $player]) {
        DesignatedPlayer::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'score_set' => 'league', 'lech_player_id' => $player->id]);
    }

    $hurt->update(['status' => 'excluded']);

    expect(DesignatedPlayer::where('matchday_id', $next->id)->pluck('lech_player_id')->all())->toBe([$fit->id])
        ->and(DesignatedPlayer::where('matchday_id', $played->id)->count())->toBe(1);
});

test('players still resting at the kickoff leave the line-up, almost recovered ones stay', function () {
    $user = User::factory()->create();
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $match = LechMatch::create(['opponent' => 'Legia', 'kickoff_at' => now()->subDay()]);
    $played = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia', 'is_home' => true, 'kickoff_at' => now()->subDay(), 'status' => MatchdayStatus::Played->value, 'lech_match_id' => $match->id]);
    $next = Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'Raków', 'is_home' => true, 'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value]);
    $resting = LechPlayer::create(['name' => 'Ishak', 'number' => 9, 'position' => 'forward']);
    $tired = LechPlayer::create(['name' => 'Sousa', 'number' => 10, 'position' => 'midfielder']);

    // Odpoczynek liczony od gwizdka rozegranej kolejki: 200 h kończy się długo po następnym gwizdku, 54 h tuż po nim (plaster).
    MatchAppearance::create(['lech_match_id' => $match->id, 'lech_player_id' => $resting->id, 'rest_hours' => 200]);
    MatchAppearance::create(['lech_match_id' => $match->id, 'lech_player_id' => $tired->id, 'rest_hours' => 54]);

    foreach ([$played, $next] as $matchday) {
        foreach ([$resting, $tired] as $player) {
            DesignatedPlayer::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'score_set' => 'league', 'lech_player_id' => $player->id]);
        }
    }

    Squad::forget();

    expect(Squad::dropResting())->toBe(1)
        ->and(DesignatedPlayer::where('matchday_id', $next->id)->pluck('lech_player_id')->all())->toBe([$tired->id])
        ->and(DesignatedPlayer::where('matchday_id', $played->id)->count())->toBe(2);
});
