<?php

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Bot;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Fixture;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

// Podgląd listy na nowy sezon (panel → Nowa lista sezonu): awanse, spadki i wypychanie botów na aktualnych tabelach.

test('the next season preview pushes the bots below the players', function () {
    PermissionModel::findOrCreate(Permission::SeasonList->value, 'web');
    Role::findOrCreate(RoleName::User->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::SeasonList->value);

    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $league = Competition::create(['season_id' => $season->id, 'type' => 'league', 'tier' => 1, 'name' => 'Ekstraklasa']);
    $player = User::factory()->create(['team_name' => 'Ada FC']);
    $player->assignRole(RoleName::User->value);
    $human = SeasonTeam::create(['season_id' => $season->id, 'position' => 1, 'user_id' => $player->id]);
    $robot = SeasonTeam::create(['season_id' => $season->id, 'position' => 2, 'bot_id' => Bot::create(['name' => 'Robo Lech', 'sort_order' => 1, 'is_active' => true])->id]);
    $humanEntry = CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $human->id, 'seed' => 1]);
    $robotEntry = CompetitionEntry::create(['competition_id' => $league->id, 'season_team_id' => $robot->id, 'seed' => 2]);
    // Bot wygrywa mecz, ale na nowej liście i tak ląduje pod graczem.
    Fixture::create(['competition_id' => $league->id, 'round' => 1, 'home_entry_id' => $humanEntry->id, 'away_entry_id' => $robotEntry->id, 'home_goals' => 0, 'away_goals' => 2]);

    Livewire::actingAs($admin)->test('pages::dashboard.next-season')
        ->assertSee(__('Next season list'))
        ->assertSeeInOrder(['Ekstraklasa', 'Ada FC', 'Robo Lech']);
});

test('the next season preview needs the season list permission', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard.next-season'))->assertForbidden();
});
