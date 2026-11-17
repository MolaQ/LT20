<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\DesignatedPlayer;
use App\Models\LechPlayer;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\User;
use App\Support\TeamSquad;

// Kadra drużyny na stronie zespołu (?widok=kadra): sumy w sezonach i rozgrywkach, swoja za darmo, cudza w premium.

function squadPick(User $user, Matchday $matchday, LechPlayer $player, string $set, int $matches, array $values = []): void
{
    DesignatedPlayer::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'score_set' => $set, 'lech_player_id' => $player->id,
        'counted' => true, 'matches' => $matches] + $values);
}

test('the squad sums picks per season and competition, without matchdays before the whistle', function () {
    $user = User::factory()->create();
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $played = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia', 'is_home' => true, 'kickoff_at' => now()->subWeek(), 'status' => MatchdayStatus::Played->value]);
    $second = Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'Pogoń', 'is_home' => false, 'kickoff_at' => now()->subDays(3), 'status' => MatchdayStatus::Played->value]);
    $planned = Matchday::create(['season_id' => $season->id, 'number' => 3, 'opponent' => 'Raków', 'is_home' => true, 'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value]);
    $player = LechPlayer::create(['name' => 'Ishak', 'number' => 9, 'position' => 'forward']);

    // Liga Legend: dwa pojedynki w kolejce, więc gole i doświadczenie liczą się dwa razy. Zawodnik raz w kolejce.
    squadPick($user, $played, $player, 'legends', 2, ['goals' => 2, 'assists' => 1, 'attack' => 3, 'experience' => 5.0]);
    squadPick($user, $second, $player, 'league', 1, ['goals' => 1, 'attack' => 1, 'experience' => 3.0]);
    squadPick($user, $planned, $player, 'cup', 0);

    $squad = TeamSquad::for($user->id, $season);
    $rows = collect($squad['rows'])->keyBy(2);

    expect($squad['seasons'])->toHaveCount(1)
        ->and($squad['seasons'][0]['current'])->toBeTrue()
        ->and($rows)->toHaveCount(2)
        ->and($rows['legends'][3])->toBe(2)
        ->and($rows['legends'][4])->toBe(4)
        ->and($rows['legends'][11])->toBe(10.0)
        ->and($rows['league'][4])->toBe(1)
        ->and(array_keys($squad['all']))->toBe(['league', 'legends']);
});

test('the own squad is free and other squads need premium', function () {
    config(['premium.free' => false]);
    $owner = User::factory()->create();
    $visitor = User::factory()->create();

    $this->actingAs($owner)->get(route('team.show', ['user' => $owner, 'widok' => 'kadra']))->assertOk()->assertSee(__('players fielded by this team'));
    $this->actingAs($visitor)->get(route('team.show', ['user' => $owner, 'widok' => 'kadra']))->assertOk()->assertDontSee(__('players fielded by this team'));
});
