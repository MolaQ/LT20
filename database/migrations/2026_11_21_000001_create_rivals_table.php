<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baza rywali Lecha (16.11.2026): nazwa (jak w kalendarzu meczów), skrót i 1–3 barwy. Mecz łączy się z rywalem
 * po nazwie (bez klucza obcego), więc stare mecze i kolejki działają bez zmian, a barwy pojawiają się na tablicy meczu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rivals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('short_name', 12)->nullable();
            $table->json('colors')->nullable();
            $table->timestamps();
        });

        // Rywal pierwszej kolejki sezonu I (barwy zielono-żółto-czarne).
        DB::table('rivals')->insert([
            'name' => 'GKS Katowice', 'short_name' => 'GKS', 'colors' => json_encode(['#0f8a43', '#facc15', '#111111']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rivals');
    }
};
