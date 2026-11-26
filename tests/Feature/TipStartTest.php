<?php

use App\Enums\MatchdayStatus;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Tip;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Kafelek „Wytypuj wynik” (decyzja z 26.11.2026): samo wejście na stronę typowania niczego nie zapisuje,
// dopiero kliknięcie kafelka zapisuje 0:0.

test('opening the tips page saves no tip until the player clicks the tile', function () {
    Role::findOrCreate(RoleName::User->value, 'web');
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $me = User::factory()->create(['team_name' => 'Kolejorz Kaczory']);
    $me->assignRole(RoleName::User->value);
    SeasonTeam::create(['season_id' => $season->id, 'position' => 1, 'user_id' => $me->id]);
    $next = Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'GKS Katowice', 'is_home' => false,
        'kickoff_at' => now()->addDays(2), 'status' => MatchdayStatus::Planned->value]);

    $this->actingAs($me)->get(route('tips', ['matchday_slug' => 'kolejka-1']))->assertOk()->assertSee(__('Make your tip'));
    expect(Tip::where('matchday_id', $next->id)->exists())->toBeFalse();

    Livewire::actingAs($me)->test('pages::tips', ['matchday_slug' => 'kolejka-1'])->call('startTip');

    expect(Tip::where('matchday_id', $next->id)->where('user_id', $me->id)->first()?->score())->toBe('0:0');
});
