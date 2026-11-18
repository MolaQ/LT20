<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Support\HomeStats;
use Livewire\Livewire;

// Strona startowa: liga gracza (pięć miejsc wokół niego), czołówka Ekstraklasy dla gościa, puls kolejki i ciekawostki sezonu.

function homeLeague(): array
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $league = Competition::create(['season_id' => $season->id, 'type' => 'league', 'tier' => 1, 'name' => 'Ekstraklasa']);
    $me = User::factory()->create(['team_name' => 'FC Kolejorz 1922']);

    foreach (range(1, 10) as $position) {
        $user = $position === 4 ? $me : User::factory()->create(['team_name' => 'Zespół '.$position]);
        $team = SeasonTeam::create(['season_id' => $season->id, 'position' => $position, 'user_id' => $user->id]);
        CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $team->id, 'seed' => $position]);
    }

    return [$season, $me];
}

test('the home page shows five places around the player in his league', function () {
    [, $me] = homeLeague();

    $page = Livewire::actingAs($me)->test('pages::home.welcome');
    $league = $page->instance()->myLeague;

    expect($league['rows'])->toHaveCount(5)
        ->and($league['mine']['name'])->toBe('FC Kolejorz 1922')
        ->and(collect($league['rows'])->pluck('me')->filter()->count())->toBe(1);

    $page->assertSee('Ekstraklasa')->assertSee(__('Your league'))->assertSee(__('Full table'));
});

test('a guest sees the top of Ekstraklasa', function () {
    homeLeague();

    $league = Livewire::test('pages::home.welcome')->instance()->myLeague;

    expect($league['mine'])->toBeNull()
        ->and($league['rows'])->toHaveCount(5)
        ->and($league['rows'][0]['place'])->toBe(1);
});

test('the matchday pulse counts tips and the season facts find the best tipsters', function () {
    [$season, $me] = homeLeague();
    $open = Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'GKS Katowice', 'is_home' => true, 'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value]);
    $played = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia', 'is_home' => true, 'kickoff_at' => now()->subWeek(),
        'status' => MatchdayStatus::Played->value, 'lech_goals' => 2, 'opponent_goals' => 1]);
    $other = User::where('team_name', 'Zespół 1')->firstOrFail();

    Tip::create(['matchday_id' => $open->id, 'user_id' => $me->id, 'lech_goals' => 2, 'opponent_goals' => 0, 'saved_at' => now()]);
    Tip::create(['matchday_id' => $played->id, 'user_id' => $me->id, 'lech_goals' => 2, 'opponent_goals' => 1, 'saved_at' => now()->subWeek()]);
    Tip::create(['matchday_id' => $played->id, 'user_id' => $other->id, 'lech_goals' => 0, 'opponent_goals' => 1, 'saved_at' => now()->subWeek()]);

    $live = HomeStats::live($open);
    expect($live['tips'])->toBe(1)->and($live['recent'])->toBe(['FC Kolejorz 1922']);

    $stats = HomeStats::season($season);
    expect($stats['outcome'])->toBe(50)
        ->and($stats['exact_count'])->toBe(1)
        ->and($stats['top'][0]['team'])->toBe('FC Kolejorz 1922');

    Livewire::test('pages::home.live-pulse', ['matchdayId' => $open->id])->assertSee('FC Kolejorz 1922');
});

test('the league card shows the form, the place change and the next rival with a link', function () {
    [$season, $me] = homeLeague();
    $entries = CompetitionEntry::orderBy('seed')->get()->values();
    $league = Competition::firstOrFail();
    Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia', 'is_home' => true, 'kickoff_at' => now()->subWeeks(2), 'status' => MatchdayStatus::Played->value, 'lech_goals' => 1, 'opponent_goals' => 0]);
    Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'Wisła', 'is_home' => false, 'kickoff_at' => now()->subWeek(), 'status' => MatchdayStatus::Played->value, 'lech_goals' => 1, 'opponent_goals' => 0]);
    Matchday::create(['season_id' => $season->id, 'number' => 3, 'opponent' => 'GKS Katowice', 'is_home' => true, 'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value]);

    // Kolejka 1: gracz (miejsce 4) przegrywa, kolejka 2: wygrywa wysoko i idzie w górę.
    Fixture::create(['competition_id' => $league->id, 'round' => 1, 'home_entry_id' => $entries[3]->id, 'away_entry_id' => $entries[0]->id, 'home_goals' => 0, 'away_goals' => 2]);
    Fixture::create(['competition_id' => $league->id, 'round' => 2, 'home_entry_id' => $entries[3]->id, 'away_entry_id' => $entries[1]->id, 'home_goals' => 5, 'away_goals' => 0]);
    Fixture::create(['competition_id' => $league->id, 'round' => 3, 'home_entry_id' => $entries[2]->id, 'away_entry_id' => $entries[3]->id]);

    $page = Livewire::actingAs($me)->test('pages::home.welcome');
    $data = $page->instance()->myLeague;
    $mine = collect($data['rows'])->firstWhere('me', true);

    expect($data['round'])->toBe(2)
        ->and($mine['form'])->toBe(['L', 'W'])
        ->and($mine['move'])->toBeGreaterThan(0)
        ->and($data['rival']['name'])->toBe('Zespół 3')
        ->and($data['rival']['url'])->toContain('/teams/')
        ->and($data['rows'][0]['zone'])->toBeNull();

    $page->assertSee(route('tips', ['matchday_slug' => 'kolejka-3']), false)
        ->assertSee('Zespół 3')
        ->assertDontSee(' M ·');
});
