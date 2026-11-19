<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Odpoczynek osobno dla każdego czasu gry (rest_hours_1 … rest_hours_8) zamiast podstawy i godzin za połowę.
 * Zapisane w panelu stare ustawienia przeliczamy na stopnie (podstawa + stopień / 2 × godziny za połowę), więc nic się nie zmienia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('designation_settings')) {
            return;
        }

        $saved = DB::table('designation_settings')->whereIn('key', ['rest_base_hours', 'rest_hours_per_half'])->pluck('value', 'key');

        if ($saved->isNotEmpty()) {
            $base = (float) ($saved['rest_base_hours'] ?? 10);
            $perHalf = (float) ($saved['rest_hours_per_half'] ?? 60);
            $now = now();

            DB::table('designation_settings')->upsert(
                array_map(fn (int $step) => [
                    'key' => 'rest_hours_'.$step,
                    'value' => round($base + $step / 2 * $perHalf),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], range(1, 8)),
                ['key'],
                ['value', 'updated_at'],
            );
        }

        DB::table('designation_settings')->whereIn('key', ['rest_base_hours', 'rest_hours_per_half'])->delete();
    }

    public function down(): void
    {
        // Bez powrotu: stopnie mogą być dowolne, nie da się ich zapisać jako podstawy i godzin za połowę.
    }
};
