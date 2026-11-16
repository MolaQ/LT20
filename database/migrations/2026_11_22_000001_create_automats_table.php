<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automaty (16.11.2026): zwykłe konta graczy, które same typują, wybierają skład i odpowiadają na pytania
 * o ustawionej porze przed meczem (App\Support\Automats). Znacznik widzi tylko panel admina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // popular = najczęstszy typ graczy, fixed = stały wynik (tip_lech:tip_opponent).
            $table->string('tip_mode', 10)->default('popular');
            $table->unsignedTinyInteger('tip_lech')->default(2);
            $table->unsignedTinyInteger('tip_opponent')->default(1);
            // Ile minut przed pierwszym gwizdkiem automat oddaje typ.
            $table->unsignedSmallInteger('minutes_before')->default(60);
            $table->boolean('active')->default(true);
            // Ostatnia kolejka, w której automat już zagrał, i kiedy.
            $table->foreignId('last_matchday_id')->nullable()->constrained('matchdays')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automats');
    }
};
