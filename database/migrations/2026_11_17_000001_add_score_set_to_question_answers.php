<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pytania osobno do każdego typu rozgrywek (decyzja MolaQ z 13.11.2026): gracz odpowiada na te same pytania kolejki
 * oddzielnie w lidze, Pucharze Polski, pucharach europejskich i Lidze Legend, i w każdym sam wybiera stronę (atak/obrona).
 * Dotychczasowe odpowiedzi (wspólne dla wszystkich typów) kopiujemy do każdego typu, żeby nic się nie zmieniło w rozliczeniu.
 */
return new class extends Migration
{
    private const SETS = ['league', 'cup', 'champions', 'legends'];

    public function up(): void
    {
        Schema::table('question_answers', function (Blueprint $table) {
            $table->string('score_set', 16)->default('league')->after('user_id');
        });

        // Najpierw nowy indeks (zaczyna się od matchday_question_id, więc obsłuży też klucz obcy w MySQL), potem stary.
        Schema::table('question_answers', function (Blueprint $table) {
            $table->unique(['matchday_question_id', 'user_id', 'score_set']);
        });
        Schema::table('question_answers', function (Blueprint $table) {
            $table->dropUnique(['matchday_question_id', 'user_id']);
        });

        $now = now();
        foreach (DB::table('question_answers')->where('score_set', 'league')->get() as $row) {
            foreach (array_slice(self::SETS, 1) as $set) {
                DB::table('question_answers')->insert([
                    'matchday_question_id' => $row->matchday_question_id,
                    'user_id' => $row->user_id,
                    'score_set' => $set,
                    'answer' => $row->answer,
                    'side' => $row->side,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('question_answers')->where('score_set', '!=', 'league')->delete();

        Schema::table('question_answers', function (Blueprint $table) {
            $table->unique(['matchday_question_id', 'user_id']);
        });
        Schema::table('question_answers', function (Blueprint $table) {
            $table->dropUnique(['matchday_question_id', 'user_id', 'score_set']);
            $table->dropColumn('score_set');
        });
    }
};
