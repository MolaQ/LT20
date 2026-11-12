<?php

use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamLike;
use App\Models\User;
use Livewire\Livewire;

// Klub użytkownika: wyświetlenia (raz na sesję, bez właściciela) i polubienia (niebieskie serduszko).

test('a team view is counted once per session and not for the owner', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();

    $this->actingAs($visitor)->get(route('team.show', $owner))->assertOk();
    expect($owner->fresh()->profile_views)->toBe(1);

    // Ta sama sesja (klub już oglądany) nie liczy się drugi raz.
    $this->actingAs($visitor)->withSession(['team-viewed.'.$owner->id => true])->get(route('team.show', $owner))->assertOk();
    expect($owner->fresh()->profile_views)->toBe(1);

    $this->actingAs($owner)->get(route('team.show', $owner))->assertOk();
    expect($owner->fresh()->profile_views)->toBe(1);
});

test('a player can like a team and take the like back', function () {
    $owner = User::factory()->create();
    $fan = User::factory()->create();

    $page = Livewire::actingAs($fan)->test('pages::team-profile', ['user' => $owner]);

    $page->call('toggleLike');
    expect(TeamLike::where('user_id', $owner->id)->count())->toBe(1);

    $page->call('toggleLike');
    expect(TeamLike::where('user_id', $owner->id)->count())->toBe(0);
});

test('nobody can like their own team', function () {
    $owner = User::factory()->create();

    Livewire::actingAs($owner)->test('pages::team-profile', ['user' => $owner])
        ->call('toggleLike')
        ->assertForbidden();
});

test('competitions the team does not play in this season still get a tile', function () {
    $owner = User::factory()->create();
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $team = SeasonTeam::create(['season_id' => $season->id, 'position' => 1, 'user_id' => $owner->id]);
    $league = Competition::create(['season_id' => $season->id, 'type' => 'league', 'tier' => 1]);
    $cup = Competition::create(['season_id' => $season->id, 'type' => 'cup']);
    Competition::create(['season_id' => $season->id, 'type' => 'champions']);
    Competition::create(['season_id' => $season->id, 'type' => 'legends']);
    CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $team->id, 'seed' => 1]);
    CompetitionEntry::create(['competition_id' => $cup->id, 'season_team_id' => $team->id, 'seed' => 1]);

    $tiles = Livewire::test('pages::team-profile', ['user' => $owner])->instance()->seasonCompetitions;

    // Zawsze cztery miejsca: liga, puchar, puchary europejskie i Liga Legend (dwa ostatnie bez udziału zespołu).
    expect(array_column($tiles, 'missing'))->toBe([false, false, true, true])
        ->and($tiles[2]['status'])->toBe(__('Did not qualify'))
        ->and($tiles[3]['status'])->toBe(__('Not taking part'));
});
