<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Przywrócenie gracza ze statusu Inactive zeruje serię kolejek bez typu: kolejki sprzed tej chwili
 * nie liczą się już do serii (FinishSeason::inactiveUsers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('inactivity_reset_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('inactivity_reset_at');
        });
    }
};
