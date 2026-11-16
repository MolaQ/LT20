<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strategia składu automatu (16.11.2026): w każdym typie rozgrywek strategia, liczba zawodników i strategia na mecz
 * z botem, kolejność rozgrywek przy wyborze, własna kolejność zawodników oraz skład poprawiony ręcznie na najbliższą
 * kolejkę (App\Support\Automats::plan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automats', function (Blueprint $table) {
            // typ rozgrywek (league, cup, champions, legends) => {strategy, count, bot}: strategia (experience, priority,
            // random, none), liczba zawodników i strategia na mecz z botem (same = jak w rozgrywkach).
            $table->json('squad_rules')->nullable()->after('minutes_before');
            // Kolejność typów rozgrywek przy wyborze zawodników (pierwsze biorą najlepszych).
            $table->json('squad_order')->nullable()->after('squad_rules');
            // Własna kolejność zawodników (id z kadry Lecha) do strategii „priority”.
            $table->json('squad_priority')->nullable()->after('squad_order');
            // Skład poprawiony ręcznie: {matchday_id, sets: {typ rozgrywek: [id zawodników]}}.
            $table->json('squad_override')->nullable()->after('squad_priority');
        });
    }

    public function down(): void
    {
        Schema::table('automats', function (Blueprint $table) {
            $table->dropColumn(['squad_rules', 'squad_order', 'squad_priority', 'squad_override']);
        });
    }
};
