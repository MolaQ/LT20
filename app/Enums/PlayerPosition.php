<?php

namespace App\Enums;

/**
 * Pozycja zawodnika z kadry Lecha. Przy desygnowaniu od pozycji zależy punktacja za gola i czyste konto.
 */
enum PlayerPosition: string
{
    case Goalkeeper = 'goalkeeper';
    case Defender = 'defender';
    case Midfielder = 'midfielder';
    case Forward = 'forward';

    public function label(): string
    {
        return match ($this) {
            self::Goalkeeper => __('Goalkeeper'),
            self::Defender => __('Defender'),
            self::Midfielder => __('Midfielder'),
            self::Forward => __('Forward'),
        };
    }

    /** Skrót do tabel: B, O, P, N. */
    public function short(): string
    {
        return match ($this) {
            self::Goalkeeper => __('GK'),
            self::Defender => __('DF'),
            self::Midfielder => __('MF'),
            self::Forward => __('FW'),
        };
    }

    /**
     * Wspólna paleta pozycji (18.11.2026): B bursztynowy, O morski, P indygo, N różowy. Te same kolory w kadrze,
     * gwiazdach, składach i legendach, żeby nowy gracz od razu kojarzył pozycję z kolorem.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Goalkeeper => 'bg-amber-500 text-amber-950',
            self::Defender => 'bg-teal-600 text-white',
            self::Midfielder => 'bg-indigo-600 text-white',
            self::Forward => 'bg-pink-600 text-white',
        };
    }

    /** @return array<string, string> Klasy koloru według wartości pozycji. */
    public static function tones(): array
    {
        return array_combine(self::values(), array_map(fn (self $p) => $p->tone(), self::cases()));
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
