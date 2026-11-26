<?php

use App\Enums\MatchdayStatus;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Support\MatchdayGraphics;
use App\Support\Players;
use Spatie\Permission\Models\Role;

// Gracze dołączający w trakcie sezonu: liczą się do „z ilu” dopiero od kolejki po dołączeniu, a grafika „Nowi gracze”
// pokazuje, kto dołączył między kolejkami i do której ligi trafił.

function newPlayersSeason(): array
{
    Role::findOrCreate(RoleName::User->value, 'web');
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $first = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'GKS Katowice', 'is_home' => false,
        'kickoff_at' => now()->subDays(3), 'status' => MatchdayStatus::Played->value]);
    $second = Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'Legia Warszawa', 'is_home' => true,
        'kickoff_at' => now()->addDays(2), 'status' => MatchdayStatus::Planned->value]);

    return [$season, $first, $second];
}

function newPlayersTeam(Season $season, int $position, string $name, $joinedAt): SeasonTeam
{
    $user = User::factory()->create(['team_name' => $name]);
    $user->assignRole(RoleName::User->value);

    return SeasonTeam::create(['season_id' => $season->id, 'position' => $position, 'user_id' => $user->id, 'joined_at' => $joinedAt]);
}

test('a player who joined after kickoff does not count for that matchday', function () {
    [$season, $first, $second] = newPlayersSeason();
    newPlayersTeam($season, 1, 'Stary Wyjadacz', now()->subWeek());
    newPlayersTeam($season, 2, 'Nowy Kibic', now()->subDay());

    expect(Players::expected($first)->count())->toBe(1)
        ->and(Players::expected($second)->count())->toBe(2);
});

test('a saved tip always counts, even after a late join', function () {
    [$season, $first] = newPlayersSeason();
    $late = newPlayersTeam($season, 1, 'Spóźniony', now()->subDay());
    Tip::create(['matchday_id' => $first->id, 'user_id' => $late->user_id, 'lech_goals' => 1, 'opponent_goals' => 0, 'saved_at' => now()->subDays(4)]);

    expect(Players::expected($first)->count())->toBe(1);
});

test('taking a bot slot stamps the join time', function () {
    [$season] = newPlayersSeason();
    $slot = SeasonTeam::create(['season_id' => $season->id, 'position' => 5]);
    expect($slot->joined_at)->toBeNull();

    $slot->update(['user_id' => User::factory()->create()->id]);

    expect($slot->fresh()->joined_at)->not->toBeNull();
});

test('new players list the teams that joined since the previous matchday with their leagues', function () {
    [$season, $first, $second] = newPlayersSeason();
    newPlayersTeam($season, 1, 'Stary Wyjadacz', now()->subWeek());
    newPlayersTeam($season, 15, 'Nowy Kibic', now()->subDay());
    newPlayersTeam($season, 101, 'Podwórko FC', now()->subHours(2));

    $newcomers = MatchdayGraphics::newcomers($second);

    expect(array_column($newcomers, 'team'))->toBe(['Nowy Kibic', 'Podwórko FC'])
        ->and($newcomers[0]['league'])->toBe('I liga')
        ->and($newcomers[1]['league'])->toStartWith('Liga podwórkowa')
        ->and(MatchdayGraphics::newcomers($first))->toBe([]);
});
