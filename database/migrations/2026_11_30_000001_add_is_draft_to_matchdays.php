<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Szkic kolejki (decyzja MolaQ z 24.11.2026): kolejka z nowo dodanym meczem czeka jako szkic, aż admin
 * ułoży pytania i ją opublikuje. Szkicu nie widać u graczy i nie da się go typować. Istniejące kolejki
 * zostają opublikowane (domyślnie false), więc nic się nie zmienia w trwającym typowaniu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
