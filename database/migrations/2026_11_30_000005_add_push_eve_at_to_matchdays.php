<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Znacznik przypomnienia push dzień przed meczem (App\Support\Push::eve), żeby poszło raz. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->timestamp('push_eve_at')->nullable()->after('push_open_at');
        });
    }

    public function down(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->dropColumn('push_eve_at');
        });
    }
};
