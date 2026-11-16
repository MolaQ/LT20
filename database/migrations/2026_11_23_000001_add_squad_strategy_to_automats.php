<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strategia składu automatu (16.11.2026): osobno dla każdego typu rozgrywek i dla meczu z botem, własna kolejność
 * zawodników oraz skład poprawiony ręcznie na najbliższą kolejkę (App\Support\Automats::plan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automats', function (Blueprint $table) {
            // typ rozgrywek (league, cup, champions, legends) => strategia (experience, priority, random, none)
            $table->json('squad_rules')->nullable()->after('minutes_before');
            // Strategia, gdy w tych rozgrywkach rywalem jest bot (same = jak w rozgrywkach).
            $table->string('squad_vs_bot', 12)->default('same')->after('squad_rules');
            // Własna kolejność zawodników (id z kadry Lecha) do strategii „priority”.
            $table->json('squad_priority')->nullable()->after('squad_vs_bot');
            // Skład poprawiony ręcznie: {matchday_id, sets: {typ rozgrywek: [id zawodników]}}.
            $table->json('squad_override')->nullable()->after('squad_priority');
        });
    }

    public function down(): void
    {
        Schema::table('automats', function (Blueprint $table) {
            $table->dropColumn(['squad_rules', 'squad_vs_bot', 'squad_priority', 'squad_override']);
        });
    }
};
