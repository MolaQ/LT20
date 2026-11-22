<?php

use App\Enums\MatchdayStatus;
use App\Enums\Permission;
use App\Enums\SeasonStatus;
use App\Models\Bot;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\News;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Support\MatchdaySummary;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Podsumowanie kolejki (Promocja → Podsumowanie kolejki): mokre szmaty, Liga Legend, typy kibiców i szkic artykułu.

function summaryMatchday(): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia Warszawa', 'is_home' => true,
        'kickoff_at' => now()->subDay(), 'status' => MatchdayStatus::Played->value, 'lech_goals' => 2, 'opponent_goals' => 1]);
}

function summaryEntry(Competition $competition, array $team): CompetitionEntry
{
    $seasonTeam = SeasonTeam::create($team + ['season_id' => $competition->season_id]);

    return CompetitionEntry::create(['competition_id' => $competition->id, 'season_team_id' => $seasonTeam->id, 'seed' => $seasonTeam->position]);
}

test('wet rags are the human teams beaten by a bot', function () {
    $matchday = summaryMatchday();
    $league = Competition::create(['season_id' => $matchday->season_id, 'type' => 'league', 'tier' => 1, 'name' => 'Ekstraklasa']);
    $bot = Bot::create(['name' => 'Robo Lech', 'sort_order' => 1, 'is_active' => true]);
    $human = summaryEntry($league, ['position' => 1, 'user_id' => User::factory()->create(['team_name' => 'Ada FC'])->id]);
    $robot = summaryEntry($league, ['position' => 2, 'bot_id' => $bot->id]);
    $winner = summaryEntry($league, ['position' => 3, 'user_id' => User::factory()->create(['team_name' => 'Bob United'])->id]);
    $robot2 = summaryEntry($league, ['position' => 4, 'bot_id' => Bot::create(['name' => 'Robo Kolejorz', 'sort_order' => 2, 'is_active' => true])->id]);
    Fixture::create(['competition_id' => $league->id, 'round' => 1, 'home_entry_id' => $human->id, 'away_entry_id' => $robot->id, 'home_goals' => 1, 'away_goals' => 3]);
    Fixture::create(['competition_id' => $league->id, 'round' => 1, 'home_entry_id' => $winner->id, 'away_entry_id' => $robot2->id, 'home_goals' => 2, 'away_goals' => 0]);

    expect(MatchdaySummary::wetRags($matchday))->toBe([
        ['team' => 'Ada FC', 'bot' => 'Robo Lech', 'score' => '1:3', 'competition' => 'Ekstraklasa'],
    ]);
});

test('legends out lists the teams eliminated in this matchday', function () {
    $matchday = summaryMatchday();
    $legends = Competition::create(['season_id' => $matchday->season_id, 'type' => 'legends', 'name' => 'Liga Legend']);
    $out = summaryEntry($legends, ['position' => 1, 'user_id' => User::factory()->create(['team_name' => 'Cezary'])->id]);
    $out->update(['eliminated_round' => 1]);
    summaryEntry($legends, ['position' => 2, 'user_id' => User::factory()->create(['team_name' => 'Darek'])->id]);

    expect(MatchdaySummary::legendsOut($matchday))->toBe(['Cezary']);
});

test('the most popular tips show the percent and whether they hit', function () {
    $matchday = summaryMatchday();
    foreach ([[2, 1], [2, 1], [1, 0], [3, 0]] as [$lech, $rival]) {
        Tip::create(['matchday_id' => $matchday->id, 'user_id' => User::factory()->create()->id, 'lech_goals' => $lech, 'opponent_goals' => $rival, 'saved_at' => now()]);
    }

    $scores = MatchdaySummary::scores($matchday);

    expect($scores[0])->toBe(['score' => '2:1', 'count' => 2, 'percent' => 50, 'hit' => true])
        ->and($scores)->toHaveCount(3);
});

test('the article has the score, the fans tips and the wet rags', function () {
    $matchday = summaryMatchday();
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => User::factory()->create()->id, 'lech_goals' => 2, 'opponent_goals' => 1, 'saved_at' => now()]);

    $article = MatchdaySummary::article($matchday->fresh());

    expect($article['title'])->toContain('2:1')
        ->and($article['body'])->toContain('<h2>')
        ->and($article['body'])->toContain(__('How the fans tipped'))
        ->and($article['body'])->toContain(__('Wet rags'));
});

test('an admin turns the summary into a news draft', function () {
    foreach ([Permission::SeasonList, Permission::NewsCreate] as $permission) {
        PermissionModel::findOrCreate($permission->value, 'web');
    }
    $admin = User::factory()->create();
    $admin->givePermissionTo([Permission::SeasonList->value, Permission::NewsCreate->value]);
    $matchday = summaryMatchday();

    Livewire::actingAs($admin)->test('pages::dashboard.matchday-summary', ['matchdayId' => $matchday->id])
        ->assertSee(__('Matchday summary'))
        ->call('createDraft')
        ->assertRedirect(route('dashboard.news', ['status' => 'draft']));

    $news = News::sole();
    expect($news->published_at)->toBeNull()
        ->and($news->title)->toContain('2:1');
});
