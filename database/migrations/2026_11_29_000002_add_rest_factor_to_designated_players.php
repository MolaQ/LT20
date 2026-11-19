<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zmęczony zawodnik (19.11.2026): wystawiony pod koniec regeneracji gra normalnie, ale jego następna regeneracja
 * trwa dłużej. rest_factor = mnożnik godzin regeneracji tego wyboru (1 = zwykle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designated_players', function (Blueprint $table) {
            $table->decimal('rest_factor', 4, 2)->default(1)->after('rest_hours');
        });
    }

    public function down(): void
    {
        Schema::table('designated_players', function (Blueprint $table) {
            $table->dropColumn('rest_factor');
        });
    }
};
