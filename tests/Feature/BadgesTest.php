<?php

use App\Enums\MatchdayStatus;
use App\Enums\SeasonStatus;
use App\Models\Friendly;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\TeamLike;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\Badges;
use Livewire\Livewire;

// Odznaki za osiągnięcia: liczenie z danych gry, stopnie, kolejka zdobycia i zakładka na profilu drużyny.

function badgeMatchday(Season $season, int $number, int $lech, int $opponent): Matchday
{
    return Matchday::create(['season_id' => $season->id, 'number' => $number, 'opponent' => 'Legia', 'is_home' => true,
        'kickoff_at' => now()->subDays(10 - $number), 'status' => MatchdayStatus::Played->value, 'lech_goals' => $lech, 'opponent_goals' => $opponent]);
}

function badgeTip(Matchday $matchday, User $user, int $lech, int $opponent, int $order): void
{
    Tip::create(['matchday_id' => $matchday->id, 'user_id' => $user->id, 'lech_goals' => $lech, 'opponent_goals' => $opponent,
        'saved_at' => now(), 'save_order' => $order]);
}

test('a rare exact score earns prophet, against the tide and early bird in that matchday', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = badgeMatchday($season, 1, 0, 2);
    $ewa = User::factory()->create(['team_name' => 'Ewa FC']);
    badgeTip($matchday, $ewa, 0, 2, 1);
    foreach (range(1, 20) as $i) {
        badgeTip($matchday, User::factory()->create(), 2, 0, 100 + $i);
    }

    Badges::rebuild($matchday);

    $badges = Badges::forUser($ewa->id);
    expect($badges['prophet']['tier'])->toBe(1)
        ->and($badges['prophet']['next'])->toBe(3)
        ->and($badges['against']['tier'])->toBe(1)
        ->and($badges['early_bird']['tier'])->toBe(1)
        ->and(UserBadge::where('user_id', $ewa->id)->where('badge', 'prophet')->value('matchday_id'))->toBe($matchday->id);

    $earned = collect(Badges::earnedIn($matchday))->where('name', 'Ewa FC')->pluck('badge')->all();
    expect($earned)->toContain('prophet', 'against', 'early_bird');
});

test('correct outcomes in consecutive matchdays build a streak and a missing tip breaks it', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $ada = User::factory()->create();
    $bob = User::factory()->create();
    foreach ([1, 2, 3] as $number) {
        $matchday = badgeMatchday($season, $number, 2, 1);
        badgeTip($matchday, $ada, 1, 0, $number);
        if ($number !== 2) {
            badgeTip($matchday, $bob, 3, 0, 10 + $number);
        }
    }

    Badges::rebuild();

    expect(Badges::forUser($ada->id)['streak'])->toMatchArray(['progress' => 3, 'tier' => 1])
        ->and(Badges::forUser($bob->id)['streak'])->toMatchArray(['progress' => 1, 'tier' => 0]);
});

test('community badges count club likes and confirmed invitations', function () {
    $owner = User::factory()->create();
    foreach (range(1, 10) as $i) {
        TeamLike::create(['user_id' => $owner->id, 'liker_id' => User::factory()->create()->id]);
    }
    User::factory()->count(3)->create(['referred_by' => $owner->id]);
    User::factory()->unverified()->create(['referred_by' => $owner->id]);

    Badges::rebuild();

    $badges = Badges::forUser($owner->id);
    expect($badges['favourite']['tier'])->toBe(1)
        ->and($badges['ambassador']['progress'])->toBe(3)
        ->and($badges['ambassador']['tier'])->toBe(1);
});

test('a higher tier is only recorded once and one-time badges are gold', function () {
    expect(Badges::tierFor('prophet', 0))->toBe(0)
        ->and(Badges::tierFor('prophet', 4))->toBe(2)
        ->and(Badges::tierFor('prophet', 9))->toBe(3)
        ->and(Badges::color('unbeaten', 1))->toBe(Badges::TIER_COLORS[3])
        ->and(Badges::color('prophet', 1))->toBe(Badges::TIER_COLORS[1]);
});

test('the team profile shows the badges tab', function () {
    $owner = User::factory()->create(['team_name' => 'Ada FC']);

    Livewire::actingAs($owner)->test('pages::team-profile', ['user' => $owner])
        ->set('tab', 'odznaki')
        ->assertSee(__('Prophet'))
        ->assertSee(__('Ambassador'));
});

test('tips in consecutive matchdays earn the regular badge and won friendlies the sparring badge', function () {
    $old = Season::create(['number' => 1, 'status' => SeasonStatus::Finished->value]);
    $new = Season::create(['number' => 2, 'status' => SeasonStatus::Active->value]);
    $ada = User::factory()->create();
    $bob = User::factory()->create();
    $order = 0;
    foreach ([[$old, 1], [$old, 2], [$old, 3], [$new, 1], [$new, 2]] as [$season, $number]) {
        $matchday = badgeMatchday($season, $number, 1, 0);
        badgeTip($matchday, $ada, 0, 0, ++$order);
        if ($number !== 2) {
            badgeTip($matchday, $bob, 0, 0, ++$order);
        }
    }
    Friendly::create(['matchday_id' => $matchday->id, 'challenger_id' => $ada->id, 'opponent_id' => $bob->id, 'token' => 'abc',
        'challenger_set' => 'cup', 'opponent_set' => 'cup', 'status' => 'accepted', 'challenger_goals' => 2, 'opponent_goals' => 1, 'settled_at' => now()]);

    Badges::rebuild($matchday);

    expect(Badges::forUser($ada->id)['regular']['tier'])->toBe(1)
        ->and(Badges::forUser($ada->id)['regular']['progress'])->toBe(5)
        ->and(Badges::forUser($bob->id)['regular']['progress'])->toBe(2)
        ->and(Badges::forUser($ada->id)['sparring']['tier'])->toBe(1)
        ->and(Badges::forUser($bob->id)['sparring']['progress'])->toBe(0);
});
