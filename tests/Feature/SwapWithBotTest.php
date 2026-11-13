<?php

use App\Enums\Permission;
use App\Enums\SeasonStatus;
use App\Models\Bot;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\User;
use App\Support\Roster;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Zamiana gracza z botem po zatwierdzeniu sezonu, ale przed pierwszą rozliczoną kolejką: zmienia się tylko właściciel miejsc.

function swapSeason(SeasonStatus $status = SeasonStatus::Active): array
{
    $season = Season::create(['number' => 1, 'status' => $status->value]);
    $player = User::factory()->create(['team_name' => 'Kolejorz FC']);
    $bot = Bot::create(['sort_order' => 1, 'name' => 'Bot Ekstraklasy', 'is_active' => true]);

    // Bot na 1. miejscu (Ekstraklasa), gracz na 101. (Liga podwórkowa).
    $botSlot = SeasonTeam::create(['season_id' => $season->id, 'position' => 1, 'bot_id' => $bot->id]);
    $playerSlot = SeasonTeam::create(['season_id' => $season->id, 'position' => 101, 'user_id' => $player->id]);

    $legends = Competition::create(['season_id' => $season->id, 'type' => 'legends', 'name' => 'Liga Legend']);
    CompetitionEntry::create(['competition_id' => $legends->id, 'season_team_id' => $playerSlot->id, 'seed' => 1]);

    return [$season, $player, $bot, $botSlot, $playerSlot, $legends];
}

test('a player takes a bot place and the bot takes the old place of the player', function () {
    [$season, $player, $bot, $botSlot, $playerSlot, $legends] = swapSeason();

    expect(Roster::canSwap($season))->toBeTrue()
        ->and(DB::transaction(fn () => Roster::swapWithBot($playerSlot, $botSlot)))->toBeTrue();

    expect($botSlot->fresh()->user_id)->toBe($player->id)
        ->and($botSlot->fresh()->bot_id)->toBeNull()
        ->and($botSlot->fresh()->position)->toBe(1)
        ->and($playerSlot->fresh()->bot_id)->toBe($bot->id)
        ->and($playerSlot->fresh()->user_id)->toBeNull()
        // Wpis w Lidze Legend idzie razem z graczem.
        ->and(CompetitionEntry::where('competition_id', $legends->id)->value('season_team_id'))->toBe($botSlot->id);
});

test('swapping is closed in a draft and after the first scored matchday', function () {
    [$season] = swapSeason(SeasonStatus::Draft);
    expect(Roster::canSwap($season))->toBeFalse();

    $season->update(['status' => SeasonStatus::Active->value]);
    expect(Roster::canSwap($season->fresh()))->toBeTrue();

    Matchday::create(['season_id' => $season->id, 'number' => 1, 'opponent' => 'GKS Katowice', 'is_home' => true, 'kickoff_at' => now(), 'status' => 'played']);
    expect(Roster::canSwap($season->fresh()))->toBeFalse();
});

test('an admin swaps a player with the highest ranked bot of a league on the team list page', function () {
    [$season, $player, , $botSlot] = swapSeason();
    PermissionModel::findOrCreate(Permission::SeasonList->value, 'web');
    PermissionModel::findOrCreate(Permission::SeasonEdit->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo([Permission::SeasonList->value, Permission::SeasonEdit->value]);

    Livewire::actingAs($admin)->test('pages::dashboard.season-teams', ['seasonId' => $season->id])
        ->set('seasonId', $season->id)
        ->call('openSwap', SeasonTeam::where('user_id', $player->id)->value('id'))
        ->set('swapTier', 1)
        ->call('swapTeam')
        ->assertDispatched('ui-toast');

    expect($botSlot->fresh()->user_id)->toBe($player->id);
});
