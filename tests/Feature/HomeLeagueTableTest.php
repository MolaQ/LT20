<?php

use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\User;
use Livewire\Livewire;

// Strona startowa: tabela ligi zalogowanego gracza (cała liga) z wyróżnionym zespołem gracza.

test('the home page shows the whole league table of the player', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $league = Competition::create(['season_id' => $season->id, 'type' => 'league', 'tier' => 1, 'name' => 'Ekstraklasa']);
    $me = User::factory()->create(['team_name' => 'FC Kolejorz 1922']);

    foreach (range(1, 10) as $position) {
        $user = $position === 4 ? $me : User::factory()->create(['team_name' => 'Zespół '.$position]);
        $team = SeasonTeam::create(['season_id' => $season->id, 'position' => $position, 'user_id' => $user->id]);
        CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $team->id, 'seed' => $position]);
    }

    $page = Livewire::actingAs($me)->test('pages::home.welcome');

    expect($page->instance()->myLeague['rows'])->toHaveCount(10)
        ->and(collect($page->instance()->myLeague['rows'])->firstWhere('me', true)['name'])->toBe('FC Kolejorz 1922');

    $page->assertSee('Ekstraklasa')->assertSee('Zespół 10')->assertSee(__('Full table'));
});

test('a guest sees no league table', function () {
    Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    expect(Livewire::test('pages::home.welcome')->instance()->myLeague)->toBeNull();
});
