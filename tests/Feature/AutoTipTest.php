<?php

use App\Actions\Tips\SaveTip;
use App\Enums\MatchdayStatus;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use Spatie\Permission\Models\Role;

// Automatyczne 0:0 (decyzja z 26.11.2026): tylko na najbliższą kolejkę i tylko dla gracza, który otworzył stronę
// typowania po publikacji meczu. Dalsze opublikowane kolejki zostają bez typu.

function autoTipSetup(): array
{
    Role::findOrCreate(RoleName::User->value, 'web');
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $me = User::factory()->create(['team_name' => 'Kolejorz Kaczory']);
    $me->assignRole(RoleName::User->value);
    SeasonTeam::create(['season_id' => $season->id, 'position' => 1, 'user_id' => $me->id]);
    $next = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'GKS Katowice', 'is_home' => false,
        'kickoff_at' => now()->addDays(2), 'status' => MatchdayStatus::Planned->value]);
    $later = Matchday::create(['season_id' => $season->id, 'number' => 2, 'opponent' => 'Pogoń Szczecin', 'is_home' => true,
        'kickoff_at' => now()->addDays(9), 'status' => MatchdayStatus::Planned->value]);

    return [$me, $next, $later];
}

test('opening the tips page saves an automatic 0:0 only for the nearest matchday', function () {
    [$me, $next, $later] = autoTipSetup();

    $this->actingAs($me)->get(route('tips', ['matchday_slug' => 'kolejka-1']))->assertOk();
    $this->actingAs($me)->get(route('tips', ['matchday_slug' => 'kolejka-2']))->assertOk();

    $tip = Tip::where('matchday_id', $next->id)->where('user_id', $me->id)->first();
    expect($tip)->not->toBeNull()
        ->and($tip->score())->toBe('0:0')
        ->and($tip->is_auto)->toBeTrue()
        ->and(Tip::where('matchday_id', $later->id)->exists())->toBeFalse();
});

test('an automatic tip never overwrites the player tip and the player own save clears the flag', function () {
    [$me, $next] = autoTipSetup();
    $save = app(SaveTip::class);

    $save->handle($me, $next, 2, 1);
    $save->handle($me, $next, 0, 0, auto: true);
    expect(Tip::where('user_id', $me->id)->first()->score())->toBe('2:1');

    Tip::query()->delete();
    $save->handle($me, $next, 0, 0, auto: true);
    $save->handle($me, $next, 0, 0);
    expect(Tip::where('user_id', $me->id)->first()->is_auto)->toBeFalse();
});
