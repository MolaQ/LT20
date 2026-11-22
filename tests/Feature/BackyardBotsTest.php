<?php

use App\Enums\League;
use App\Enums\SeasonStatus;
use App\Models\Bot;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\User;
use App\Support\Promotion;
use App\Support\Roster;
use Illuminate\Support\Facades\DB;

// Liga podwórkowa a boty (decyzje z 22.11.2026): nowi gracze w trakcie sezonu zapełniają ligi wężykiem (1.1 → 1.4, potem 1.4 → 1.1),
// a po sezonie miejsca botów w lidze wyżej biorą najlepsi ze wspólnej tabeli lig 1.x (drugie miejsca, potem trzecie …).

test('new players fill the backyard leagues like a snake', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    // Ligi 1.1–1.4 (miejsca 101–140) obsadzone botami.
    foreach (range(101, 140) as $position) {
        $bot = Bot::create(['sort_order' => $position, 'name' => 'Bot '.$position, 'is_active' => true]);
        SeasonTeam::create(['season_id' => $season->id, 'position' => $position, 'bot_id' => $bot->id]);
    }

    $positions = [];
    foreach (range(1, 6) as $i) {
        $user = User::factory()->create();
        DB::transaction(fn () => Roster::place($season->id, $user->id, League::Podworkowa));
        $positions[] = SeasonTeam::where('season_id', $season->id)->where('user_id', $user->id)->value('position');
    }

    // 1.1, 1.2, 1.3, 1.4, potem z powrotem 1.4 i 1.3 (w lidze zawsze najwyżej położony bot).
    expect($positions)->toBe([101, 111, 121, 131, 132, 122]);
});

test('a league with more people waits until the others catch up', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    foreach (range(101, 140) as $position) {
        // 1.1 ma już trzech ludzi, pozostałe ligi samych botów.
        $human = $position <= 103;
        SeasonTeam::create(['season_id' => $season->id, 'position' => $position,
            'user_id' => $human ? User::factory()->create()->id : null,
            'bot_id' => $human ? null : Bot::create(['sort_order' => $position, 'name' => 'Bot '.$position, 'is_active' => true])->id]);
    }

    $user = User::factory()->create();
    DB::transaction(fn () => Roster::place($season->id, $user->id, League::Podworkowa));

    expect(SeasonTeam::where('season_id', $season->id)->where('user_id', $user->id)->value('position'))->toBe(111);
});

test('after the season bot places above go to the best of the shared table of the 1.x leagues', function () {
    $team = fn (int $id, bool $human, int $points = 0) => ['id' => $id, 'human' => $human, 'rank' => [-$points]];
    // C klasa: 4 ludzi i 6 botów (4 boty spadają, 2 zostają do wypchnięcia).
    $leagues = ['10' => ['parent' => null, 'up' => 4, 'teams' => [
        ...array_map(fn ($id) => $team($id, true), range(1, 4)),
        ...array_map(fn ($id) => $team($id, false), range(5, 10)),
    ]]];
    // Ligi 1.1–1.4: punkty 1., 2. i 3. miejsca; 2. miejsca mają 16, 19, 20 i 14 pkt.
    foreach (['p1' => [20, 16, 10], 'p2' => [21, 19, 9], 'p3' => [22, 20, 8], 'p4' => [18, 14, 7]] as $key => $points) {
        $base = (int) substr($key, 1) * 100;
        $teams = [];
        foreach (range(1, 10) as $place) {
            $teams[] = $team($base + $place, true, $points[$place - 1] ?? 0);
        }
        $leagues[$key] = ['parent' => '10', 'up' => 1, 'group' => 'backyard-1', 'teams' => $teams];
    }

    $result = Promotion::applyTree($leagues);

    // Zwycięzcy 1.x awansują, a dwa miejsca botów biorą najlepsze drugie miejsca: 1.3 (20 pkt) i 1.2 (19 pkt).
    expect($result['10'])->toBe([1, 2, 3, 4, 101, 201, 301, 401, 302, 202])
        // Drugie miejsce z 1.1 (16 pkt) zostaje w swojej lidze, a boty kończą listę w kolejności.
        ->and($result['p1'])->toContain(102)
        ->and(array_slice($result['p4'], -6))->toBe([5, 6, 7, 8, 9, 10]);
});
