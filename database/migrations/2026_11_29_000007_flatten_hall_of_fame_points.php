<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spłaszczenie Hall of Fame (20.11.2026): nowe wartości punktacji zapisane w ustawieniach z panelu
 * (mecze ligowe 3/1 bez mnożnika, mnożniki 3 → 1, mistrz 100, drugie 50, trzecie 30, awans 15).
 * Pozostałe ustawienia (puchary, Liga Legend, nagrody) zostają. Zakończone sezony przelicza przycisk
 * „Przelicz sezony” w panelu Hall of Fame, bieżący przelicza się po najbliższej kolejce.
 */
return new class extends Migration
{
    private const VALUES = [
        'multiplier_1' => 3, 'multiplier_2' => 2.6, 'multiplier_3' => 2.3, 'multiplier_4' => 2, 'multiplier_5' => 1.8,
        'multiplier_6' => 1.6, 'multiplier_7' => 1.4, 'multiplier_8' => 1.3, 'multiplier_9' => 1.2, 'multiplier_10' => 1.1,
        'multiplier_11' => 1,
        'league_champion' => 100, 'league_second' => 50, 'league_third' => 30, 'league_promotion' => 15,
        'league_top_scorer' => 10, 'league_win' => 3, 'league_draw' => 1,
    ];

    public function up(): void
    {
        foreach (self::VALUES as $key => $value) {
            DB::table('hall_of_fame_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        // Bez cofania: poprzednie wartości były ustawione ręcznie w panelu.
    }
};
