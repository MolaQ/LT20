<?php

use App\Support\Designation;
use App\Support\MatchReport;

// Regeneracja po meczu: osiem stopni czasu gry, każdy z własną liczbą godzin z ustawień.

test('the playing time maps to one of eight recovery steps', function () {
    expect(MatchReport::restStep(0, 0))->toBe(0)
        ->and(MatchReport::restStep(0, 1))->toBe(1)
        ->and(MatchReport::restStep(1, 0))->toBe(2)
        ->and(MatchReport::restStep(1, 1))->toBe(3)
        ->and(MatchReport::restStep(2, 0))->toBe(4)
        ->and(MatchReport::restStep(4, 0))->toBe(8);
});

test('every step takes its own hours from the settings', function () {
    $settings = array_map('floatval', Designation::DEFAULTS);

    expect(MatchReport::restHours(2, 0, $settings))->toBe(130)
        ->and(MatchReport::restHours(0, 1, $settings))->toBe(40)
        ->and(MatchReport::restHours(4, 0, ['rest_hours_8' => 300.0] + $settings))->toBe(300)
        ->and(MatchReport::restHours(0, 0, $settings))->toBe(0);
});
