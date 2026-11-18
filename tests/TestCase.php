<?php

namespace Tests;

use App\Support\Champions;
use App\Support\CompetitionHistory;
use App\Support\HallOfFame;
use App\Support\Ownership;
use App\Support\PlayerStats;
use App\Support\SeasonArchive;
use App\Support\Squad;
use App\Support\Standings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Once;
use Laravel\Fortify\Features;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
    /**
     * Klasy z pamięcią podręczną w statycznych tablicach (na czas jednego żądania). W testach baza jest czyszczona,
     * a identyfikatory się powtarzają, więc pamięć z poprzedniego testu podsuwała cudze dane (18.11.2026).
     *
     * @var list<class-string>
     */
    private const STATIC_CACHES = [
        Champions::class,
        CompetitionHistory::class,
        HallOfFame::class,
        Ownership::class,
        PlayerStats::class,
        SeasonArchive::class,
        Squad::class,
        Standings::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Każdy test zaczyna z pustą pamięcią podręczną.
        foreach (self::STATIC_CACHES as $class) {
            foreach ((new ReflectionClass($class))->getProperties() as $property) {
                if ($property->isStatic() && (string) $property->getType() === 'array') {
                    $property->setValue(null, []);
                }
            }
        }
        Once::flush();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
