<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsorzy (19.11.2026): baner sponsora sezonu (szeroka grafika jako tło paska sezonu na stronie głównej)
 * i własny napis przy logo („Sponsor sezonu”, „Patron medialny”…) dla sponsora sezonu i sponsorów rozgrywek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->string('sponsor_banner_path')->nullable()->after('sponsor_logo_path');
            $table->string('sponsor_label', 40)->nullable()->after('sponsor_name');
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('label', 40)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['sponsor_banner_path', 'sponsor_label']);
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
