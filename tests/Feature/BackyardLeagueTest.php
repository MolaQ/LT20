<?php

use App\Actions\Seasons\BuildListFromPrevious;
use App\Models\SeasonTeam;
use App\Support\Backyard;
use App\Support\Promotion;

// Nowa Liga podwórkowa: ligi po 10 (1.1–1.4, 2.1–2.16 …), zwycięzca w górę, po jednym spadkowiczu do każdej ligi pod spodem.

test('leagues are numbered level by level', function () {
    expect(Backyard::label(1))->toBe('1.1')
        ->and(Backyard::label(4))->toBe('1.4')
        ->and(Backyard::label(5))->toBe('2.1')
        ->and(Backyard::label(20))->toBe('2.16')
        ->and(Backyard::label(21))->toBe('3.1')
        ->and(Backyard::label(85))->toBe('4.1');
});

test('new leagues open in turn under each league above', function () {
    expect(Backyard::parent(1))->toBeNull()
        ->and(Backyard::parent(5))->toBe(1)   // 2.1 pod 1.1
        ->and(Backyard::parent(8))->toBe(4)   // 2.4 pod 1.4
        ->and(Backyard::parent(9))->toBe(1)   // 2.5 pod 1.1
        ->and(Backyard::parent(37))->toBe(5); // 3.17 pod 2.1
});

test('relegation places equal the open leagues below, at most four', function () {
    // 6 lig: 1.1–1.4, 2.1 i 2.2
    expect(Backyard::relegations(0, 6))->toBe(4)
        ->and(Backyard::relegations(1, 6))->toBe(1)
        ->and(Backyard::relegations(3, 6))->toBe(0)
        ->and(Backyard::relegations(1, 20))->toBe(4)
        ->and(Backyard::relegations(5, 42))->toBe(2) // 2.1: 3.1 i 3.17
        ->and(Backyard::children(5, 42))->toBe([21, 37]);
});

test('list positions map to leagues of ten', function () {
    expect(Backyard::forPosition(100))->toBeNull()
        ->and(Backyard::forPosition(101))->toBe(1)
        ->and(Backyard::forPosition(110))->toBe(1)
        ->and(Backyard::forPosition(141))->toBe(5)
        ->and(Backyard::firstPosition(5))->toBe(141)
        ->and(Backyard::count(520))->toBe(42)
        ->and(SeasonTeam::listSize(30))->toBe(520)
        ->and(SeasonTeam::listSize(523))->toBe(530);
});

function byLeague(int &$id, int $count = 10): array
{
    $teams = [];
    for ($i = 0; $i < $count; $i++) {
        $teams[] = ['id' => ++$id, 'human' => true];
    }

    return $teams;
}

test('c klasa sends one team to each 1.x league and each winner goes up', function () {
    $id = 0;
    $leagues = ['10' => ['parent' => null, 'up' => 4, 'teams' => byLeague($id)]];
    foreach ([1, 2, 3, 4] as $division) {
        $leagues['p'.$division] = ['parent' => '10', 'up' => 1, 'teams' => byLeague($id)];
    }
    $leagues['p5'] = ['parent' => 'p1', 'up' => 1, 'teams' => byLeague($id)];

    $result = Promotion::applyTree($leagues);

    // C klasa: 6 najlepszych zostaje, dochodzą zwycięzcy 1.1–1.4.
    expect($result['10'])->toBe([1, 2, 3, 4, 5, 6, 11, 21, 31, 41])
        // 1.1: spadkowicz z C klasy, bez zwycięzcy i ostatniego (spada do 2.1), zwycięzca 2.1.
        ->and($result['p1'])->toBe([7, 12, 13, 14, 15, 16, 17, 18, 19, 51])
        ->and($result['p4'])->toBe([10, 42, 43, 44, 45, 46, 47, 48, 49, 50])
        ->and($result['p5'][0])->toBe(20)
        ->and(array_map('count', $result))->each->toBe(10);
});

test('the old single backyard table is spread over the 1.x leagues', function () {
    expect(BuildListFromPrevious::spreadRelegated(range(1, 40), 4))
        ->toBe([1, ...range(5, 13), 2, ...range(14, 22), 3, ...range(23, 31), 4, ...range(32, 40)]);
});
