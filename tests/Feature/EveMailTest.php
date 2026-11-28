<?php

use App\Enums\MatchdayStatus;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\EveReminder;
use App\Support\EveMail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Mail przed kolejką: termin wybiera gracz, raz na kolejkę, tylko bez typu, z wypisaniem linkiem.

function eveMatchday(int $hours): Matchday
{
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    return Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => now()->addHours($hours)->subMinutes(10),
        'status' => MatchdayStatus::Planned->value,
    ]);
}

function evePlayer(Matchday $matchday, int $hours = 24): User
{
    Role::findOrCreate(RoleName::User->value, 'web');
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill(['email_reminder_hours' => $hours])->save();
    $user->assignRole(RoleName::User->value);
    SeasonTeam::create(['season_id' => $matchday->season_id, 'position' => $user->id, 'user_id' => $user->id]);

    return $user;
}

test('players get the email at the time they chose, once and only without a tip', function () {
    Notification::fake();
    $matchday = eveMatchday(24);
    $daily = evePlayer($matchday, 24);
    $early = evePlayer($matchday, 48);
    $late = evePlayer($matchday, 6);
    $off = evePlayer($matchday, 0);
    $tipped = evePlayer($matchday, 24);
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $tipped->id, 'lech_goals' => 1, 'opponent_goals' => 0, 'saved_at' => now()]);

    expect(EveMail::tick())->toBe(2)
        ->and(EveMail::tick())->toBe(0);

    Notification::assertSentTo([$daily, $early], EveReminder::class);
    Notification::assertNotSentTo([$late, $off, $tipped], EveReminder::class);
});

test('no email in the last two hours before kick-off', function () {
    Notification::fake();
    $matchday = eveMatchday(2);
    evePlayer($matchday, 3);

    expect(EveMail::tick())->toBe(0);
});

test('the signed link turns the email reminders off', function () {
    $matchday = eveMatchday(24);
    $user = evePlayer($matchday);

    $this->get(URL::signedRoute('email.reminders.off', ['user' => $user->id]))->assertOk();
    expect($user->fresh()->email_reminder_hours)->toBe(0);

    $this->get(route('email.reminders.off', ['user' => $user->id]))->assertForbidden();
});

test('the player picks the reminder time in the settings', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::settings.notifications')->set('emailHours', 6);
    expect($user->fresh()->email_reminder_hours)->toBe(6);

    Livewire::actingAs($user)->test('pages::settings.notifications')->set('emailHours', 7);
    expect($user->fresh()->email_reminder_hours)->toBe(0);
});
