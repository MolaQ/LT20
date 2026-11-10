<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klub użytkownika (strona /teams/{user}): licznik wyświetleń (oko) i polubienia (niebieskie serduszko).
 * Wyświetlenie liczy się raz na sesję odwiedzającego, bez wejść właściciela. Polubić można raz, drugi klik cofa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('profile_views')->default(0);
        });

        Schema::create('team_likes', function (Blueprint $table) {
            $table->id();
            // Właściciel polubionego klubu.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Kto polubił.
            $table->foreignId('liker_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'liker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_likes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_views');
        });
    }
};
