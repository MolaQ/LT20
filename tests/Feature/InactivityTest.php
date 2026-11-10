<?php

use App\Models\User;
use App\Support\Inactivity;
use Illuminate\Support\Facades\DB;

// Seria kolejek bez typu do ostrzeżenia „kartki”: przechodzi przez sezony, liczy tylko kolejki z meczem gracza,
// zeruje się po typie i po przywróceniu ze statusu Inactive.

/**
 * Rozegrane kolejki: [numer sezonu => [numer kolejki => 'tip' | 'miss' | 'none' (gracz bez meczu)]].
 *
 * @param  array<int, array<int, string>>  $plan
 */
function inactivityPlan(User $user, array $plan): void
{
    $now = now();
    foreach ($plan as $seasonNumber => $matchdays) {
        $seasonId = DB::table('seasons')->insertGetId(['number' => $seasonNumber, 'status' => 'finished', 'created_at' => $now, 'updated_at' => $now]);
        $teamId = DB::table('season_teams')->insertGetId(['season_id' => $seasonId, 'position' => 1, 'user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($matchdays as $number => $state) {
            $matchdayId = DB::table('matchdays')->insertGetId([
                'season_id' => $seasonId, 'number' => $number, 'status' => 'played',
                'kickoff_at' => $now->copy()->subDays(100 - $seasonNumber * 10 - $number), 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($state !== 'none') {
                DB::table('team_scores')->insert(['matchday_id' => $matchdayId, 'season_team_id' => $teamId, 'user_id' => $user->id, 'score_set' => 'league', 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($state === 'tip') {
                DB::table('tips')->insert(['matchday_id' => $matchdayId, 'user_id' => $user->id, 'lech_goals' => 1, 'opponent_goals' => 0, 'saved_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}

test('the streak carries over from the previous season', function () {
    $user = User::factory()->create();
    inactivityPlan($user, [1 => [1 => 'tip', 2 => 'miss', 3 => 'miss'], 2 => [1 => 'miss', 2 => 'none']]);

    expect(Inactivity::streak($user))->toBe(3);
});

test('a tip ends the streak', function () {
    $user = User::factory()->create();
    inactivityPlan($user, [1 => [1 => 'miss', 2 => 'miss', 3 => 'tip', 4 => 'miss']]);

    expect(Inactivity::streak($user))->toBe(1);
});

test('restoring from inactive resets the streak', function () {
    $user = User::factory()->create();
    inactivityPlan($user, [1 => [1 => 'miss', 2 => 'miss', 3 => 'miss']]);
    $user->forceFill(['inactivity_reset_at' => now()])->save();

    expect(Inactivity::streak($user->fresh()))->toBe(0);
});
