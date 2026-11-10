<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\PushMessage;
use App\Support\Push;
use Illuminate\Support\Facades\Notification;

// Powiadomienia push: otwarcie typowania i przypomnienie idą raz, tylko do graczy z listy sezonu z subskrypcją,
// przypomnienie tylko do tych bez typu, a wyłączony rodzaj nie przychodzi.

function pushSetup(int $hoursToKickoff = 48): array
{
    config(['webpush.vapid.public_key' => 'public', 'webpush.vapid.private_key' => 'private']);
    Notification::fake();

    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create([
        'season_id' => $season->id,
        'number' => 1,
        'opponent' => 'Legia',
        'is_home' => true,
        'kickoff_at' => now()->addHours($hoursToKickoff),
        'status' => MatchdayStatus::Planned->value,
    ]);

    $users = collect(range(1, 3))->map(function (int $i) use ($season) {
        $user = User::factory()->create();
        SeasonTeam::create(['season_id' => $season->id, 'position' => $i, 'user_id' => $user->id]);
        $user->updatePushSubscription('https://push.example/'.$i, 'key', 'token', 'aes128gcm');

        return $user;
    });

    return [$matchday, $users];
}

test('tipping open goes once to subscribed players on the list', function () {
    [$matchday, $users] = pushSetup();
    $outsider = User::factory()->create();
    $outsider->updatePushSubscription('https://push.example/x', 'key', 'token', 'aes128gcm');

    expect(Push::tick())->toBe(3)
        ->and(Push::tick())->toBe(0)
        ->and($matchday->fresh()->push_open_at)->not->toBeNull();

    Notification::assertSentTo($users->all(), PushMessage::class);
    Notification::assertNotSentTo($outsider, PushMessage::class);
});

test('the last call skips players who have tipped and players who turned it off', function () {
    [$matchday, $users] = pushSetup(hoursToKickoff: 1);
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $users[0]->id, 'lech_goals' => 2, 'opponent_goals' => 0, 'saved_at' => now()]);
    $users[1]->forceFill(['push_prefs' => ['open' => true, 'reminder' => false, 'results' => true, 'rival' => true]])->save();

    Push::tick();

    Notification::assertSentToTimes($users[0], PushMessage::class, 1); // tylko „typowanie otwarte”
    Notification::assertSentToTimes($users[1], PushMessage::class, 1);
    Notification::assertSentToTimes($users[2], PushMessage::class, 2);
});

test('nothing is sent without VAPID keys', function () {
    pushSetup();
    config(['webpush.vapid.public_key' => null]);

    expect(Push::tick())->toBe(0);
    Notification::assertNothingSent();
});

test('a player can choose the kinds of notifications', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('notifications.edit'))->assertOk();

    expect($user->wantsPush('results'))->toBeTrue();

    $user->forceFill(['push_prefs' => ['results' => false]])->save();

    expect($user->fresh()->wantsPush('results'))->toBeFalse()
        ->and($user->fresh()->wantsPush('open'))->toBeTrue();
});
