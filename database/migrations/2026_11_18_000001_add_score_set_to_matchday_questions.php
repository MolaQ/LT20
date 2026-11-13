<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Osobny zestaw pytań dla każdego typu rozgrywek (decyzja MolaQ z 13.11.2026): w kolejce do 5 pytań na ligę,
 * Puchar Polski, puchary europejskie i Ligę Legend, a jedno pytanie z banku trafia najwyżej do jednego zestawu.
 * Dotychczasowe pytania kolejek zostają w zestawie ligi; odpowiedzi graczy z innych typów rozgrywek (kopie z poprzedniej
 * zmiany) usuwamy, bo te pytania nie należą już do ich zestawów.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchday_questions', function (Blueprint $table) {
            $table->string('score_set', 16)->default('league')->after('matchday_id');
        });

        // Najpierw nowe indeksy (zaczynają się od matchday_id, więc obsłużą też klucz obcy w MySQL), potem stary.
        Schema::table('matchday_questions', function (Blueprint $table) {
            $table->unique(['matchday_id', 'score_set', 'position']);
            $table->unique(['matchday_id', 'question_template_id']);
        });
        Schema::table('matchday_questions', function (Blueprint $table) {
            $table->dropUnique(['matchday_id', 'position']);
        });

        DB::table('question_answers')->where('score_set', '!=', 'league')->delete();
    }

    public function down(): void
    {
        DB::table('matchday_questions')->where('score_set', '!=', 'league')->delete();

        Schema::table('matchday_questions', function (Blueprint $table) {
            $table->unique(['matchday_id', 'position']);
        });
        Schema::table('matchday_questions', function (Blueprint $table) {
            $table->dropUnique(['matchday_id', 'score_set', 'position']);
            $table->dropUnique(['matchday_id', 'question_template_id']);
            $table->dropColumn('score_set');
        });
    }
};
