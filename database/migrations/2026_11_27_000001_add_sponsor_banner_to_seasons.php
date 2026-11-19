<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baner sponsora sezonu (19.11.2026): szeroka grafika jako tło paska sezonu na stronie głównej (obok logo „powered by”).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->string('sponsor_banner_path')->nullable()->after('sponsor_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('sponsor_banner_path');
        });
    }
};
