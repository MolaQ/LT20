<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nowa Liga podwórkowa (przebudowa 2.0): ligi po 10 zespołów 1.1–1.4, 2.1–2.16 … zamiast jednej tabeli w systemie szwajcarskim.
 * Każda liga podwórkowa to osobne rozgrywki typu League z poziomem 11 i numerem ligi (division, App\Support\Backyard).
 * Ligi 1-10 i pozostałe rozgrywki mają division = 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->unsignedSmallInteger('division')->default(0)->after('tier');
        });

        // Najpierw nowy indeks, potem usunięcie starego (klucz obcy season_id musi mieć indeks przez cały czas).
        Schema::table('competitions', function (Blueprint $table) {
            $table->unique(['season_id', 'type', 'tier', 'division']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropUnique(['season_id', 'type', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->unique(['season_id', 'type', 'tier']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropUnique(['season_id', 'type', 'tier', 'division']);
            $table->dropColumn('division');
        });
    }
};
