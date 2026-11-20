<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Bot;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\TeamScore;
use App\Models\User;
use App\Support\AwardRace;

// Wyścig o nagrody (/nagrody): klasyfikacje na żywo, zmiana miejsca po ostatniej kolejce, moje miejsca i zakładka na profilu.

function raceScore(Matchday $matchday, SeasonTeam $team, string $set, array $values): void
{
    TeamScore::create(['matchday_id' => $matchday->id, 'season_team_id' => $team->id, 'user_id' => $team->user_id, 'bot_id' => $team->bot_id,
        'score_set' => $set] + $values);
}

function raceSeason(): array
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $first = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia', 'is_home' => true, 'kickoff_at' => now()->subWeek(), 'status' => MatchdayStatus::Played->value]);
    $second = Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'Pogoń', 'is_home' => false, 'kickoff_at' => now()->subDays(3), 'status' => MatchdayStatus::Played->value]);
    Matchday::create(['season_id' => $season->id, 'number' => 3, 'opponent' => 'Raków', 'is_home' => true, 'kickoff_at' => now()->addDay(), 'status' => MatchdayStatus::Planned->value]);

    $ada = User::factory()->create(['team_name' => 'Ada FC']);
    $bob = User::factory()->create(['team_name' => 'Bob United']);
    $bot = Bot::create(['name' => 'Robo Lech', 'sort_order' => 1, 'is_active' => true]);

    $teams = [
        'ada' => SeasonTeam::create(['season_id' => $season->id, 'position' => 12, 'user_id' => $ada->id]), // I liga
        'bob' => SeasonTeam::create(['season_id' => $season->id, 'position' => 3, 'user_id' => $bob->id]), // Ekstraklasa
        'bot' => SeasonTeam::create(['season_id' => $season->id, 'position' => 5, 'bot_id' => $bot->id]),
    ];

    // Kolejka 1: Bob prowadzi. Kolejka 2: Ada wyprzedza Boba w MVP.
    raceScore($first, $teams['bob'], 'league', ['outcome_hit' => true, 'offense_bonus' => 3, 'defense_bonus' => 2, 'offense' => 5]);
    raceScore($first, $teams['ada'], 'league', ['outcome_hit' => true, 'offense_bonus' => 1, 'defense_bonus' => 1, 'offense' => 2]);
    raceScore($first, $teams['bot'], 'league', ['offense_bonus' => 1, 'offense' => 1]);
    raceScore($second, $teams['ada'], 'league', ['outcome_hit' => true, 'offense_bonus' => 4, 'defense_bonus' => 2, 'offense' => 6]);
    // Puchar liczy się do Złotej Piłki i Złotego Buta, ale nie do MVP i Złotych Rękawic.
    raceScore($second, $teams['bob'], 'cup', ['outcome_hit' => true, 'offense_bonus' => 5, 'defense_bonus' => 3, 'offense' => 8]);

    return [$season, $teams, $ada, $bob];
}

test('the race ranks every award live and shows the change after the last matchday', function () {
    [$season, $teams] = raceSeason();

    $mvp = AwardRace::ranking($season, 'mvp');
    expect(array_column($mvp, 'name'))->toBe(['Ada FC', 'Bob United', 'Robo Lech'])
        ->and($mvp[0]['value'])->toBe(10) // 2 rozstrzygnięcia + 5 ataku + 3 obrony
        ->and($mvp[0]['move'])->toBe(1)
        ->and($mvp[1]['value'])->toBe(6)
        ->and($mvp[1]['move'])->toBe(-1);

    $ball = AwardRace::ranking($season, 'golden_ball');
    expect($ball[0]['name'])->toBe('Bob United')->and($ball[0]['value'])->toBe(13);

    $gloves = AwardRace::ranking($season, 'golden_gloves');
    expect($gloves[0]['value'])->toBe(3)->and($gloves[1]['value'])->toBe(2);

    // Remis bez punktów: wyższa liga, potem wyższa pozycja na liście.
    expect(AwardRace::data($season)['remaining'])->toBe(1)
        ->and(AwardRace::data($season)['last'])->toBe(2);
});

test('the awards page shows my places and opens on my row', function () {
    [, , $ada] = raceSeason();

    $this->actingAs($ada)->get(route('awards'))
        ->assertOk()
        ->assertSee(__('Race for the awards'))
        ->assertSee(__('Your places: :team', ['team' => 'Ada FC']))
        ->assertSee('Bob United');

    $this->get(route('awards', ['nagroda' => 'golden_gloves']))->assertOk()->assertSee('Robo Lech');
});

test('the team profile has an awards tab with the team place', function () {
    [, , $ada] = raceSeason();

    $this->get(route('team.show', ['user' => $ada, 'widok' => 'nagrody']))
        ->assertOk()
        ->assertSee(__('Full tables'))
        ->assertSee(__(':place. place', ['place' => 1]));
});

test('the awards page waits for the first settled matchday', function () {
    Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    $this->get(route('awards'))->assertOk()->assertSee(__('The race starts after the first settled matchday of the season.'));
});
