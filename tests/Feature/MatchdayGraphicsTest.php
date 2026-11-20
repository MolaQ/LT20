<?php

use App\Enums\MatchdayStatus;
use App\Enums\Permission;
use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\Rival;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Support\MatchdayGraphics;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Grafiki kolejki w panelu (Promocja → Grafiki kolejki): barwy drużyn, hity kolejki, „pod prąd” i sama strona.

function graphicsMatchday(array $values = []): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create($values + ['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia Warszawa', 'is_home' => false,
        'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value]);
}

test('the preview takes the rival colours and short name, with the host on the left', function () {
    Rival::create(['name' => 'Legia Warszawa', 'short_name' => 'LEG', 'colors' => ['#ffffff', '#00843d', '#d71920']]);
    $teams = MatchdayGraphics::teams(graphicsMatchday());

    expect($teams['home']['short'])->toBe('LEG')
        ->and($teams['home']['colors'])->toBe(['#ffffff', '#00843d', '#d71920'])
        ->and($teams['home']['ink'])->toBe('#0b1f4d') // ciemny napis na białym tle
        ->and($teams['away']['colors'])->toBe(Rival::LECH_COLORS)
        ->and($teams['away']['ink'])->toBe('#ffffff');
});

test('an unknown rival gets the game navy colours', function () {
    $teams = MatchdayGraphics::teams(graphicsMatchday(['opponent' => 'Nowy Klub', 'is_home' => true]));

    expect($teams['away']['colors'])->toBe(MatchdayGraphics::FALLBACK)
        ->and($teams['away']['short'])->toBe('NOW');
});

test('the top league game is the clash of the best placed teams', function () {
    $matchday = graphicsMatchday(['number' => 2]);
    $league = Competition::create(['season_id' => $matchday->season_id, 'type' => 'league', 'tier' => 1, 'name' => 'Ekstraklasa']);
    $entries = [];
    foreach (['Ada FC', 'Bob United', 'Cezary', 'Darek'] as $i => $name) {
        $team = SeasonTeam::create(['season_id' => $matchday->season_id, 'position' => $i + 1, 'user_id' => User::factory()->create(['team_name' => $name])->id]);
        $entries[] = CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $team->id, 'seed' => $i + 1]);
    }
    // Kolejka 1: Ada i Bob wygrywają, więc w kolejce 2 ich mecz jest hitem.
    Fixture::create(['competition_id' => $league->id, 'round' => 1, 'home_entry_id' => $entries[0]->id, 'away_entry_id' => $entries[2]->id, 'home_goals' => 3, 'away_goals' => 0]);
    Fixture::create(['competition_id' => $league->id, 'round' => 1, 'home_entry_id' => $entries[1]->id, 'away_entry_id' => $entries[3]->id, 'home_goals' => 2, 'away_goals' => 0]);
    Fixture::create(['competition_id' => $league->id, 'round' => 2, 'home_entry_id' => $entries[2]->id, 'away_entry_id' => $entries[3]->id]);
    Fixture::create(['competition_id' => $league->id, 'round' => 2, 'home_entry_id' => $entries[0]->id, 'away_entry_id' => $entries[1]->id]);

    $hits = MatchdayGraphics::hits($matchday);

    expect($hits)->toHaveCount(1)
        ->and($hits[0])->toMatchArray(['competition' => 'Ekstraklasa', 'home' => 'Ada FC', 'away' => 'Bob United', 'home_note' => '1.', 'away_note' => '2.']);
});

test('against the tide names the players who hit a result few people expected', function () {
    $matchday = graphicsMatchday(['lech_goals' => 0, 'opponent_goals' => 2, 'status' => MatchdayStatus::Played->value]);
    foreach (['Ada FC' => [2, 0], 'Bob United' => [1, 0], 'Cezary' => [3, 1], 'Darek' => [1, 1], 'Ewa FC' => [0, 2]] as $name => [$lech, $rival]) {
        Tip::create(['matchday_id' => $matchday->id, 'user_id' => User::factory()->create(['team_name' => $name])->id,
            'lech_goals' => $lech, 'opponent_goals' => $rival, 'saved_at' => now()]);
    }

    $against = MatchdayGraphics::against($matchday);

    expect($against)->not->toBeNull()
        ->and($against['percent'])->toBe(20)
        ->and($against['exact'])->toBeTrue()
        ->and($against['names'])->toBe(['Ewa FC']);

    // Gdy większość typowała dobrze, nie ma niespodzianki.
    $matchday->update(['lech_goals' => 2, 'opponent_goals' => 0]);
    expect(MatchdayGraphics::against($matchday->fresh()))->toBeNull();
});

test('an admin opens the matchday graphics page', function () {
    PermissionModel::findOrCreate(Permission::SeasonList->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::SeasonList->value);
    Rival::create(['name' => 'Legia Warszawa', 'short_name' => 'LEG', 'colors' => ['#ffffff', '#00843d']]);
    $matchday = graphicsMatchday();

    $this->actingAs($admin)->get(route('dashboard.matchday-graphics'))->assertOk();

    Livewire::actingAs($admin)->test('pages::dashboard.matchday-graphics', ['matchdayId' => $matchday->id])
        ->assertSee('Legia Warszawa');
});
