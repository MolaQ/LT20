<?php

use App\Enums\MatchdayStatus;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\MatchdayRecap;
use App\Support\RecapMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Podsumowanie kolejki e-mailem: rano po meczu, raz, z typem, meczem ligowym i miejscem w tabeli.

function recapMatchday(string $kickoff): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => Carbon::parse($kickoff),
        'status' => MatchdayStatus::Played->value,
        'lech_goals' => 2,
        'opponent_goals' => 1,
    ]);
}

/** @return array{0: User, 1: CompetitionEntry} */
function recapPlayer(Matchday $matchday, int $position, string $team): array
{
    Role::findOrCreate(RoleName::User->value, 'web');
    $user = User::factory()->create(['email_verified_at' => now(), 'team_name' => $team]);
    $user->assignRole(RoleName::User->value);
    $seasonTeam = SeasonTeam::create(['season_id' => $matchday->season_id, 'position' => $position, 'user_id' => $user->id]);
    $league = Competition::firstOrCreate(['season_id' => $matchday->season_id, 'type' => 'league', 'tier' => 1], ['name' => 'Ekstraklasa']);
    $entry = CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $seasonTeam->id, 'seed' => $position]);

    return [$user, $entry];
}

test('the summary goes the morning after the match, once', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-30 08:00'));
    $matchday = recapMatchday('2026-11-29 17:30');
    [$player] = recapPlayer($matchday, 1, 'Kolejorz');
    [$off] = recapPlayer($matchday, 2, 'Wiara');
    $off->forceFill(['email_recap' => false])->save();

    expect(RecapMail::tick())->toBe(0);

    $this->travelTo(Carbon::parse('2026-11-30 09:05'));
    expect(RecapMail::tick())->toBe(1)
        ->and(RecapMail::tick())->toBe(0);

    Notification::assertSentTo($player, MatchdayRecap::class);
    Notification::assertNotSentTo($off, MatchdayRecap::class);
});

test('old matchdays get no summary', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-12-10 10:00'));
    $matchday = recapMatchday('2026-11-29 17:30');
    recapPlayer($matchday, 1, 'Kolejorz');

    expect(RecapMail::tick())->toBe(0);
});

test('the summary has the tip, the league match and the place', function () {
    $matchday = recapMatchday('2026-11-29 17:30');
    [$me, $mine] = recapPlayer($matchday, 1, 'Kolejorz');
    [, $theirs] = recapPlayer($matchday, 2, 'Wiara');
    Fixture::create(['competition_id' => $mine->competition_id, 'round' => 1, 'home_entry_id' => $mine->id, 'away_entry_id' => $theirs->id, 'home_goals' => 3, 'away_goals' => 1]);
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $me->id, 'lech_goals' => 2, 'opponent_goals' => 1, 'saved_at' => now()]);

    $recap = RecapMail::for($me, $matchday);

    expect($recap['tip'])->toBe([2, 1])
        ->and($recap['duel'])->toBe(['mine' => 3, 'theirs' => 1, 'rival' => 'Wiara'])
        ->and($recap['place'])->toBe(1)
        ->and($recap['teams'])->toBe(2)
        ->and($recap['league'])->toBe('Ekstraklasa');

    $mail = (new MatchdayRecap($matchday, $recap))->toMail($me);
    expect($mail->subject)->toContain('Lech Poznań 2:1 Legia');
});

test('the summary can be turned off by link and in the settings', function () {
    $user = User::factory()->create();

    $this->get(URL::signedRoute('email.recap.off', ['user' => $user->id]))->assertOk();
    expect($user->fresh()->email_recap)->toBeFalse();

    Livewire::actingAs($user)->test('pages::settings.notifications')->set('emailRecap', true);
    expect($user->fresh()->email_recap)->toBeTrue();
});
