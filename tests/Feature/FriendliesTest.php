<?php

use App\Enums\MatchdayStatus;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Friendly;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Support\Friendlies;
use App\Support\PlayerCompetitions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Sparingi: wyzwanie i przyjęcie, sparing zajmuje wolny typ rozgrywek (kto gra we wszystkich czterech, nie zagra),
// wynik liczony jak pojedynek ligowy i bilans.

function friendlyMatchday(): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => now()->addDay(),
        'status' => MatchdayStatus::Planned->value,
    ]);
}

/** Gracz w lidze sezonu i, opcjonalnie, w innych rozgrywkach (typy: cup, champions, legends). */
function friendlyPlayer(Matchday $matchday, int $position, array $types = []): User
{
    Role::findOrCreate(RoleName::User->value, 'web');
    $user = User::factory()->create(['team_name' => 'Drużyna '.$position]);
    $user->assignRole(RoleName::User->value);
    $team = SeasonTeam::create(['season_id' => $matchday->season_id, 'position' => $position, 'user_id' => $user->id]);
    foreach (['league', ...$types] as $type) {
        $competition = Competition::firstOrCreate(['season_id' => $matchday->season_id, 'type' => $type], ['name' => ucfirst($type), 'tier' => 1]);
        CompetitionEntry::create(['competition_id' => $competition->id, 'season_team_id' => $team->id, 'seed' => $position]);
    }

    return $user;
}

test('a challenged player accepts and the friendly takes a free competition type', function () {
    $matchday = friendlyMatchday();
    $me = friendlyPlayer($matchday, 1);
    $rival = friendlyPlayer($matchday, 2, ['cup']);

    $friendly = Friendlies::challenge($me, $matchday, $rival);
    expect($friendly->status)->toBe(Friendly::PENDING)
        ->and($friendly->challenger_set)->toBe('cup');

    Friendlies::accept($friendly, $rival);
    $friendly->refresh();

    expect($friendly->status)->toBe(Friendly::ACCEPTED)
        ->and($friendly->opponent_set)->toBe('champions')
        ->and(array_map(fn ($t) => $t->value, PlayerCompetitions::types($me->id, $matchday->season_id, 1)))->toBe(['league', 'cup'])
        ->and(array_map(fn ($t) => $t->value, PlayerCompetitions::types($rival->id, $matchday->season_id, 1)))->toBe(['league', 'cup', 'champions']);
});

test('a player in all four competition types cannot play a friendly', function () {
    $matchday = friendlyMatchday();
    $me = friendlyPlayer($matchday, 1);
    $busy = friendlyPlayer($matchday, 2, ['cup', 'champions', 'legends']);

    expect(Friendlies::blocker($busy, $matchday))->not->toBeNull()
        ->and(fn () => Friendlies::challenge($busy, $matchday))->toThrow(DomainException::class)
        ->and(fn () => Friendlies::challenge($me, $matchday, $busy))->toThrow(DomainException::class);
});

test('one friendly per matchday and an open link goes to the first who accepts', function () {
    $matchday = friendlyMatchday();
    $me = friendlyPlayer($matchday, 1);
    $friend = friendlyPlayer($matchday, 2);
    $late = friendlyPlayer($matchday, 3);

    $link = Friendlies::challenge($me, $matchday);
    expect(fn () => Friendlies::challenge($me, $matchday, $friend))->toThrow(DomainException::class);

    Friendlies::accept($link, $friend);
    expect($link->fresh()->opponent_id)->toBe($friend->id)
        ->and(fn () => Friendlies::accept($link->fresh(), $late))->toThrow(DomainException::class);
});

test('the friendly is scored like a league duel and counts in the record', function () {
    $matchday = friendlyMatchday();
    $me = friendlyPlayer($matchday, 1);
    $rival = friendlyPlayer($matchday, 2);
    $friendly = Friendlies::challenge($me, $matchday, $rival);
    Friendlies::accept($friendly, $rival);
    $friendly->refresh();

    $matchday->update(['lech_goals' => 2, 'opponent_goals' => 1]);
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $me->id, 'lech_goals' => 2, 'opponent_goals' => 1, 'saved_at' => now()]);
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $rival->id, 'lech_goals' => 0, 'opponent_goals' => 3, 'saved_at' => now()]);

    Friendlies::settle($matchday->fresh(), [], [], Tip::where('matchday_id', $matchday->id)->get()->keyBy('user_id'));
    $friendly->refresh();

    expect($friendly->isSettled())->toBeTrue()
        ->and($friendly->scoreFor($me->id)[0])->toBeGreaterThan($friendly->scoreFor($me->id)[1])
        ->and(Friendlies::record($me->id))->toBe(['played' => 1, 'won' => 1, 'drawn' => 0, 'lost' => 0])
        ->and(Friendlies::record($rival->id))->toBe(['played' => 1, 'won' => 0, 'drawn' => 0, 'lost' => 1]);
});

test('the friendlies page challenges a team found by name', function () {
    $matchday = friendlyMatchday();
    $me = friendlyPlayer($matchday, 1);
    $rival = friendlyPlayer($matchday, 2);

    Livewire::actingAs($me)->test('pages::friendlies')
        ->set('search', 'Drużyna 2')
        ->assertSee('Drużyna 2')
        ->call('challenge', $rival->id);

    expect(Friendly::where('challenger_id', $me->id)->where('opponent_id', $rival->id)->exists())->toBeTrue();
});

test('the invite link remembers the token for a guest', function () {
    $matchday = friendlyMatchday();
    $me = friendlyPlayer($matchday, 1);
    $friendly = Friendlies::challenge($me, $matchday);

    $this->get(route('friendly.invite', $friendly->token))->assertOk()->assertSee('Drużyna 1');
    expect(session('friendly_token'))->toBe($friendly->token);
});

test('a played friendly can be shown off as a story graphic', function () {
    $matchday = friendlyMatchday();
    $me = friendlyPlayer($matchday, 1);
    $rival = friendlyPlayer($matchday, 2);
    $friendly = Friendlies::challenge($me, $matchday, $rival);
    Friendlies::accept($friendly, $rival);
    $friendly->refresh()->update(['challenger_goals' => 3, 'opponent_goals' => 1, 'settled_at' => now()]);

    Livewire::actingAs($me)->test('pages::friendlies')
        ->call('showStory', $friendly->id)
        ->assertSet('story', $friendly->id)
        ->assertDispatched('ui-sheet')
        ->assertSee('Drużyna 2');
});
