<?php

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\User;
use App\Support\Graphic;
use App\Support\Referrals;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

// Kampania (18.11.2026): źródło nowych kont, zaproszenia z premium, obrazki podglądu linków i grafika przed meczem.

function campaignRequest(string $uri, array $headers = []): Request
{
    $request = Request::create($uri, 'GET', server: collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper($k) => $v])->all());
    $request->setLaravelSession(new Store('test', new ArraySessionHandler(10)));

    return $request;
}

test('the first visit remembers where a guest came from', function () {
    $facebook = campaignRequest('/?fbclid=abc');
    Referrals::remember($facebook);
    expect($facebook->session()->get('visit.source'))->toBe('Facebook');

    $google = campaignRequest('/', ['referer' => 'https://www.google.pl/search?q=lech']);
    Referrals::remember($google);
    expect($google->session()->get('visit.source'))->toBe('Google');

    $forum = campaignRequest('/', ['referer' => 'https://www.kibice-lecha.pl/forum']);
    Referrals::remember($forum);
    expect($forum->session()->get('visit.source'))->toBe('kibice-lecha.pl');

    $utm = campaignRequest('/?utm_source=fb');
    Referrals::remember($utm);
    expect($utm->session()->get('visit.source'))->toBe('Facebook');
});

test('only whole domains count as a platform', function () {
    expect(Referrals::platform('m.facebook.com'))->toBe('Facebook')
        ->and(Referrals::platform('l.facebook.com'))->toBe('Facebook')
        ->and(Referrals::platform('t.co'))->toBe('X')
        ->and(Referrals::platform('box.com'))->toBeNull()
        ->and(Referrals::platform('notfacebook.com'))->toBeNull();
});

test('an invitation link marks the source and the inviting player', function () {
    $referrer = User::factory()->create();
    $request = campaignRequest('/?z='.$referrer->id.'&fbclid=x');
    Referrals::remember($request);

    expect($request->session()->get('visit.source'))->toBe(Referrals::INVITE)
        ->and($request->session()->get('visit.referrer'))->toBe($referrer->id);
});

test('registration stores the source and the inviting player', function () {
    $referrer = User::factory()->create();
    $user = User::factory()->create();
    session()->put(['visit.source' => Referrals::INVITE, 'visit.referrer' => $referrer->id]);

    Referrals::attach($user);

    expect($user->fresh()->signup_source)->toBe(Referrals::INVITE)
        ->and($user->fresh()->referred_by)->toBe($referrer->id)
        ->and(session()->has('visit.referrer'))->toBeFalse();
});

test('an invitation from a missing player is not kept', function () {
    $user = User::factory()->create();
    session()->put(['visit.source' => Referrals::INVITE, 'visit.referrer' => 999999]);

    Referrals::attach($user);

    expect($user->fresh()->signup_source)->toBeNull()
        ->and($user->fresh()->referred_by)->toBeNull();
});

test('every three confirmed invited players give two weeks of premium', function () {
    Role::findOrCreate(RoleName::User->value, 'web');
    Role::findOrCreate(RoleName::Premium->value, 'web');
    $referrer = User::factory()->create();

    $invited = collect(range(1, 4))->map(fn () => User::factory()->unverified()->create(['referred_by' => $referrer->id]));
    foreach ($invited->take(2) as $user) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }
    expect($referrer->fresh()->premium_until)->toBeNull();

    $invited[2]->markEmailAsVerified();
    event(new Verified($invited[2]));
    $referrer->refresh();
    expect($referrer->referral_rewards)->toBe(1)
        ->and($referrer->premium_until->greaterThan(now()->addDays(Referrals::REWARD_DAYS - 1)))->toBeTrue()
        ->and(Referrals::stats($referrer))->toMatchArray(['invited' => 4, 'confirmed' => 3, 'progress' => 0, 'days' => 14]);

    // Czwarty potwierdzony nie daje jeszcze nagrody.
    $invited[3]->markEmailAsVerified();
    event(new Verified($invited[3]));
    expect($referrer->fresh()->referral_rewards)->toBe(1);
});

test('the visit statistics count new accounts by source', function () {
    User::factory()->create(['signup_source' => 'Facebook']);
    User::factory()->unverified()->create(['signup_source' => 'Facebook']);
    User::factory()->create(['signup_source' => Referrals::INVITE]);

    $page = Livewire::test('pages::dashboard.site-statistics');

    expect($page->instance()->signupsBySource(false))->toMatchArray(['Facebook' => 2, __('Invitation link') => 1])
        ->and($page->instance()->signupsBySource(true))->toMatchArray(['Facebook' => 1]);
    $page->assertSee(__('New accounts by source'));
});

test('the referral card shows the invitation link to a player', function () {
    Role::findOrCreate(RoleName::User->value, 'web');
    $me = User::factory()->create();
    $me->assignRole(RoleName::User->value);

    $this->actingAs($me)->get(route('support'))
        ->assertOk()
        ->assertSee(__('Invite fans'))
        ->assertSee(e(Referrals::link($me)), false);
});

test('link previews of a table and a team are drawn on the server', function () {
    if (! Graphic::available()) {
        $this->markTestSkipped('GD with FreeType is not available.');
    }

    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $league = Competition::create(['season_id' => $season->id, 'type' => 'league', 'tier' => 1, 'name' => 'Ekstraklasa']);
    $user = User::factory()->create(['team_name' => 'Kolejorz Kaczory']);
    $team = SeasonTeam::create(['season_id' => $season->id, 'position' => 1, 'user_id' => $user->id]);
    CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $team->id, 'seed' => 1]);

    $table = $this->get(route('og.table', ['competition' => $league->id, 'round' => 1]));
    $table->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(getimagesizefromstring($table->getContent())[0])->toBe(1200);

    $this->get(route('og.team', $user))->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('the team profile points link previews to its own image', function () {
    $user = User::factory()->create(['team_name' => 'Kolejorz Kaczory']);

    $this->get(route('team.show', $user))->assertOk()->assertSee(route('og.team', $user), false);
});

test('the panel downloads a pre-match graphic for a post and a story', function () {
    if (! Graphic::available()) {
        $this->markTestSkipped('GD with FreeType is not available.');
    }

    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $matchday = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'Legia Warszawa', 'is_home' => true, 'kickoff_at' => now()->addDays(2), 'status' => 'planned']);
    PermissionModel::findOrCreate(Permission::DashboardAccess->value, 'web');
    PermissionModel::findOrCreate(Permission::SeasonList->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo([Permission::DashboardAccess->value, Permission::SeasonList->value]);

    $post = $this->actingAs($admin)->get(route('dashboard.matchday-graphic', [$matchday, 'post']));
    $post->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(getimagesizefromstring($post->getContent()))->toMatchArray([0 => 1080, 1 => 1350])
        ->and($post->headers->get('Content-Disposition'))->toContain('lech-legia-warszawa-post.png');

    $story = $this->actingAs($admin)->get(route('dashboard.matchday-graphic', [$matchday, 'story']));
    expect(getimagesizefromstring($story->getContent()))->toMatchArray([0 => 1080, 1 => 1920]);

    $this->actingAs(User::factory()->create())->get(route('dashboard.matchday-graphic', [$matchday, 'post']))->assertRedirect();
});
