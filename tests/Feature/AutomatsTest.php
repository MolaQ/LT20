<?php

use App\Enums\MatchdayStatus;
use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Automat;
use App\Models\Bot;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\DesignatedPlayer;
use App\Models\Fixture;
use App\Models\LechMatch;
use App\Models\LechPlayer;
use App\Models\MatchAppearance;
use App\Models\Matchday;
use App\Models\MatchdayQuestion;
use App\Models\QuestionAnswer;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Support\Automats;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

// Automaty: same typują, wybierają skład i odpowiadają na pytania o ustawionej porze przed meczem.

function automatMatchday(int $minutesToKickoff = 30): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => now()->addMinutes($minutesToKickoff),
        'status' => MatchdayStatus::Planned->value,
    ]);
}

/** Gracz z rolą User na liście sezonu i w lidze. */
function automatPlayer(Matchday $matchday, int $position): User
{
    Role::findOrCreate(RoleName::User->value, 'web');
    $user = User::factory()->create();
    $user->assignRole(RoleName::User->value);
    $team = SeasonTeam::create(['season_id' => $matchday->season_id, 'position' => $position, 'user_id' => $user->id]);
    $league = Competition::firstOrCreate(['season_id' => $matchday->season_id, 'type' => 'league', 'tier' => 1], ['name' => 'Ekstraklasa']);
    CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $team->id, 'seed' => $position]);

    return $user;
}

function tipOf(User $user, Matchday $matchday, int $lech, int $opponent): void
{
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'lech_goals' => $lech, 'opponent_goals' => $opponent, 'saved_at' => now()]);
}

test('the most common tip wins, a tie goes to the score better for Lech and other automats do not count', function () {
    $matchday = automatMatchday();
    expect(Automats::popularTip($matchday))->toBe([1, 0]);

    foreach ([[1, 1], [1, 1], [2, 1], [2, 1], [0, 3]] as $i => [$lech, $opponent]) {
        tipOf(automatPlayer($matchday, $i + 1), $matchday, $lech, $opponent);
    }
    expect(Automats::popularTip($matchday))->toBe([2, 1]);

    // Trzy automaty z 0:3 nie przesądzają o najczęstszym typie.
    foreach ([6, 7, 8] as $position) {
        $bot = automatPlayer($matchday, $position);
        Automat::create(['user_id' => $bot->id]);
        tipOf($bot, $matchday, 0, 3);
    }
    expect(Automats::popularTip($matchday))->toBe([2, 1]);
});

test('an automat plays once in its window: tip, squad by experience and four answers', function () {
    $matchday = automatMatchday(30);
    $user = automatPlayer($matchday, 1);
    $automat = Automat::create(['user_id' => $user->id, 'tip_mode' => 'fixed', 'tip_lech' => 3, 'tip_opponent' => 1, 'minutes_before' => 60]);

    // Kadra: 8 zawodników, jeden niedostępny; doświadczenie z meczu Lecha ustala kolejność.
    $players = collect(range(1, 8))->map(fn ($n) => LechPlayer::create(['name' => 'Zawodnik '.$n, 'number' => $n, 'position' => 'midfielder']));
    $players[7]->update(['status' => 'excluded']);
    $match = LechMatch::create(['opponent' => 'Radomiak', 'kickoff_at' => now()->subWeek()]);
    foreach ([5 => 3.0, 6 => 2.0, 2 => 1.0] as $index => $experience) {
        MatchAppearance::create(['lech_match_id' => $match->id, 'lech_player_id' => $players[$index]->id, 'experience' => $experience]);
    }

    // Pytania: odpowiedzi graczy z różną zgodnością.
    $questions = collect(range(1, 5))->map(fn ($n) => MatchdayQuestion::create(['matchday_id' => $matchday->id, 'score_set' => 'league', 'position' => $n, 'text' => 'Pytanie '.$n]));
    $voters = collect(range(2, 5))->map(fn ($position) => automatPlayer($matchday, $position));
    $votes = [
        0 => [true, true, true, false],   // 75% tak
        1 => [false, false, false, false], // 100% nie
        2 => [true, false],                // 50%
        3 => [true, true, true, true],     // 100% tak
        4 => [false, false, false, true],  // 75% nie
    ];
    foreach ($votes as $q => $answers) {
        foreach ($answers as $v => $answer) {
            QuestionAnswer::create(['matchday_question_id' => $questions[$q]->id, 'user_id' => $voters[$v]->id, 'score_set' => 'league', 'answer' => $answer]);
        }
    }

    expect(Automats::run())->toBe(1);

    $tip = Tip::where('matchday_id', $matchday->id)->where('user_id', $user->id)->firstOrFail();
    expect([$tip->lech_goals, $tip->opponent_goals])->toBe([3, 1]);

    $squad = DesignatedPlayer::where('matchday_id', $matchday->id)->where('user_id', $user->id)->orderBy('id')->pluck('lech_player_id')->all();
    expect($squad)->toBe([$players[5]->id, $players[6]->id, $players[2]->id, $players[0]->id, $players[1]->id, $players[3]->id]);

    $mine = QuestionAnswer::where('user_id', $user->id)->get()->keyBy('matchday_question_id');
    expect($mine)->toHaveCount(4)
        ->and($mine[$questions[3]->id]->only('answer', 'side'))->toBe(['answer' => true, 'side' => 'attack'])
        ->and($mine[$questions[1]->id]->only('answer', 'side'))->toBe(['answer' => false, 'side' => 'attack'])
        ->and($mine[$questions[0]->id]->only('answer', 'side'))->toBe(['answer' => true, 'side' => 'defense'])
        ->and($mine[$questions[4]->id]->only('answer', 'side'))->toBe(['answer' => false, 'side' => 'defense'])
        ->and($mine->has($questions[2]->id))->toBeFalse();

    expect($automat->fresh()->last_matchday_id)->toBe($matchday->id)
        ->and(Automats::run())->toBe(0);
});

test('an automat waits until its time before kickoff', function () {
    $matchday = automatMatchday(90);
    $user = automatPlayer($matchday, 1);
    Automat::create(['user_id' => $user->id, 'minutes_before' => 60]);

    expect(Automats::run())->toBe(0)
        ->and(Tip::where('user_id', $user->id)->exists())->toBeFalse();
});

test('an admin finds a team by name or email and makes it an automat', function () {
    Role::findOrCreate(RoleName::Admin->value, 'web');
    Role::findOrCreate(RoleName::User->value, 'web');
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $team = User::factory()->create(['team_name' => 'Kolejorz z Wildy', 'email' => 'wilda@example.com']);
    $team->assignRole(RoleName::User->value);
    $other = User::factory()->create(['team_name' => 'Bułgarska Ekipa', 'email' => 'bulgarska@example.com']);
    $other->assignRole(RoleName::User->value);

    $page = Livewire::actingAs($admin)->test('pages::dashboard.automats')
        ->call('add')
        ->set('search', 'z wildy')
        ->assertSee('Kolejorz z Wildy')
        ->assertSee('(wilda@example.com)')
        ->assertDontSee('Bułgarska Ekipa')
        ->set('search', 'bulgarska@')
        ->assertSee('Bułgarska Ekipa');

    $page->call('pick', $team->id)
        ->set('tipMode', 'fixed')
        ->set('tipLech', 2)
        ->set('tipOpponent', 0)
        ->set('minutesBefore', 45)
        ->call('save')
        ->assertHasNoErrors();

    $automat = Automat::where('user_id', $team->id)->firstOrFail();
    expect($automat->only('tip_mode', 'tip_lech', 'tip_opponent', 'minutes_before'))->toBe(['tip_mode' => 'fixed', 'tip_lech' => 2, 'tip_opponent' => 0, 'minutes_before' => 45]);

    // Drużyna, która już jest automatem, nie podpowiada się drugi raz i nie da się jej dodać ponownie.
    $page->call('add')
        ->set('search', 'z wildy')
        ->assertDontSee('(wilda@example.com)')
        ->call('pick', $team->id)
        ->call('save')
        ->assertHasErrors('userId');
});

test('the automats page is only for the admin', function () {
    // Moderator z dostępem do panelu, ale bez roli Admin, wraca na pulpit.
    $permissions = [Permission::DashboardAccess->value, Permission::SeasonList->value];
    foreach ($permissions as $permission) {
        PermissionModel::findOrCreate($permission, 'web');
    }
    $moderator = User::factory()->create();
    $moderator->givePermissionTo($permissions);

    $this->actingAs($moderator)->get(route('dashboard.automats'))->assertRedirect(route('dashboard'));
});

test('the squad follows the strategy of each competition, the bot strategy and a squad set by hand', function () {
    $matchday = automatMatchday(30);
    $user = automatPlayer($matchday, 1);
    $myTeam = SeasonTeam::where('user_id', $user->id)->firstOrFail();
    $league = Competition::where('season_id', $matchday->season_id)->where('type', 'league')->firstOrFail();
    $cup = Competition::create(['season_id' => $matchday->season_id, 'type' => 'cup', 'name' => 'Puchar Polski']);

    // Liga: rywalem jest bot. Puchar: rywalem jest gracz.
    $bot = Bot::create(['sort_order' => 9001, 'name' => 'Automatyczny Bot Testowy']);
    $botTeam = SeasonTeam::create(['season_id' => $matchday->season_id, 'position' => 50, 'bot_id' => $bot->id]);
    $human = automatPlayer($matchday, 2);
    $humanTeam = SeasonTeam::where('user_id', $human->id)->firstOrFail();
    $myLeague = CompetitionEntry::where('competition_id', $league->id)->where('season_team_id', $myTeam->id)->firstOrFail();
    $botLeague = CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $botTeam->id, 'seed' => 50]);
    $myCup = CompetitionEntry::create(['competition_id' => $cup->id, 'season_team_id' => $myTeam->id, 'seed' => 1]);
    $humanCup = CompetitionEntry::create(['competition_id' => $cup->id, 'season_team_id' => $humanTeam->id, 'seed' => 2]);
    Fixture::create(['competition_id' => $league->id, 'round' => 1, 'home_entry_id' => $myLeague->id, 'away_entry_id' => $botLeague->id]);
    Fixture::create(['competition_id' => $cup->id, 'round' => 1, 'home_entry_id' => $humanCup->id, 'away_entry_id' => $myCup->id]);

    $players = collect(range(1, 4))->map(fn ($n) => LechPlayer::create(['name' => 'Zawodnik '.$n, 'number' => $n, 'position' => 'midfielder']));
    // Puchar wybiera pierwszy (3 zawodników, własna kolejność), liga po nim; w lidze rywalem jest bot, więc nikt.
    $automat = Automat::create([
        'user_id' => $user->id,
        'squad_rules' => ['league' => ['strategy' => 'experience', 'count' => 6, 'bot' => 'none'], 'cup' => ['strategy' => 'priority', 'count' => 3, 'bot' => 'same']],
        'squad_order' => ['cup', 'league'],
        'squad_priority' => [$players[3]->id, $players[1]->id],
    ]);

    $plan = Automats::plan($automat->fresh('user'), $matchday);
    expect(array_keys($plan))->toBe(['cup', 'league'])
        ->and($plan['league'])->toMatchArray(['bot' => true, 'strategy' => 'none', 'players' => [], 'manual' => false])
        ->and($plan['cup'])->toMatchArray(['bot' => false, 'strategy' => 'priority', 'manual' => false])
        ->and($plan['cup']['players'])->toBe([$players[3]->id, $players[1]->id, $players[0]->id]);

    // Skład poprawiony ręcznie na tę kolejkę ma pierwszeństwo; zawodnik z pucharu nie trafia już do ligi.
    $automat->update(['squad_override' => ['matchday_id' => $matchday->id, 'sets' => ['league' => [$players[0]->id, $players[2]->id]]]]);
    expect(Automats::pickSquad($automat->fresh('user'), $matchday))->toBe([
        'cup' => [$players[3]->id, $players[1]->id, $players[0]->id],
        'league' => [$players[2]->id],
    ]);
});

test('the least experienced strategy starts from players without experience', function () {
    $matchday = automatMatchday(30);
    $user = automatPlayer($matchday, 1);
    $players = collect(range(1, 3))->map(fn ($n) => LechPlayer::create(['name' => 'Zawodnik '.$n, 'number' => $n, 'position' => 'midfielder']));
    $match = LechMatch::create(['opponent' => 'Radomiak', 'kickoff_at' => now()->subWeek()]);
    MatchAppearance::create(['lech_match_id' => $match->id, 'lech_player_id' => $players[0]->id, 'experience' => 2.0]);
    MatchAppearance::create(['lech_match_id' => $match->id, 'lech_player_id' => $players[2]->id, 'experience' => 1.0]);

    $automat = Automat::create(['user_id' => $user->id, 'squad_rules' => ['league' => ['strategy' => 'least', 'count' => 6, 'bot' => 'same']]]);

    expect(Automats::plan($automat->fresh('user'), $matchday)['league']['players'])
        ->toBe([$players[1]->id, $players[2]->id, $players[0]->id]);
});
