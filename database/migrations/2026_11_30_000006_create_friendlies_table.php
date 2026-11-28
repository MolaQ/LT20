<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sparingi (mecze towarzyskie, 27.11.2026): dwóch graczy w jednej kolejce, każdy w swoim wolnym typie rozgrywek
 * (App\Support\Friendlies). Wyzwanie do konkretnego gracza albo otwarty link (opponent_id pusty do przyjęcia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendlies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('challenger_set', 20);
            $table->string('opponent_set', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('challenger_goals')->nullable();
            $table->unsignedSmallInteger('opponent_goals')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['matchday_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendlies');
    }
};
