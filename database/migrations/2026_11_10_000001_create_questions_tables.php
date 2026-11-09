<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pytania w kolejce (powrót pytań, przebudowa 2.0):
 *  - question_templates: bank gotowych pytań tak/nie, z którego admin układa kolejkę,
 *  - matchday_questions: 5 pytań kolejki z prawidłową odpowiedzią po meczu (null = jeszcze nie ma, voided = unieważnione),
 *  - question_answers: odpowiedź gracza (tak/nie) i strona bonusu (attack/defense); brak wiersza = pytanie pominięte,
 *  - team_scores.question_offense / question_defense: bonus z pytań w rozliczeniu (zawarty też w offense_bonus/defense_bonus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_templates', function (Blueprint $table) {
            $table->id();
            $table->string('text', 160);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamps();
        });

        Schema::create('matchday_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('text', 160);
            $table->boolean('correct')->nullable();
            $table->boolean('voided')->default(false);
            $table->timestamps();

            $table->unique(['matchday_id', 'position']);
        });

        Schema::create('question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matchday_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('answer');
            $table->string('side', 8)->default('attack');
            $table->timestamps();

            $table->unique(['matchday_question_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('team_scores', function (Blueprint $table) {
            $table->unsignedTinyInteger('question_offense')->default(0)->after('defense_bonus');
            $table->unsignedTinyInteger('question_defense')->default(0)->after('question_offense');
        });

        // Startowy bank pytań (admin dopisuje kolejne w panelu).
        $now = now();
        DB::table('question_templates')->insert(collect([
            'Czy Lech strzeli gola w 1. połowie?',
            'Czy Lech wygra 1. połowę?',
            'Czy w meczu padną więcej niż 2 gole?',
            'Czy obie drużyny strzelą gola?',
            'Czy Lech zachowa czyste konto?',
            'Czy w meczu będzie rzut karny?',
            'Czy w meczu będzie czerwona kartka?',
            'Czy Lech strzeli gola po stałym fragmencie gry?',
            'Czy Lech strzeli pierwszy?',
            'Czy padnie gol w ostatnich 15 minutach?',
            'Czy rezerwowy Lecha strzeli gola?',
            'Czy Lech strzeli gola głową?',
            'Czy mecz zakończy się remisem do przerwy?',
            'Czy sędzia pokaże więcej niż 4 żółte kartki?',
            'Czy Lech będzie miał więcej rzutów rożnych niż rywal?',
        ])->map(fn ($text) => ['text' => $text, 'is_active' => true, 'times_used' => 0, 'created_at' => $now, 'updated_at' => $now])->all());
    }

    public function down(): void
    {
        Schema::table('team_scores', function (Blueprint $table) {
            $table->dropColumn(['question_offense', 'question_defense']);
        });
        Schema::dropIfExists('question_answers');
        Schema::dropIfExists('matchday_questions');
        Schema::dropIfExists('question_templates');
    }
};
