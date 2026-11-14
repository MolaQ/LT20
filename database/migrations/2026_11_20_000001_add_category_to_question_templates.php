<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorie pytań w banku (14.11.2026), np. Posiadanie piłki, Kartki, Podania: bank w panelu jest pogrupowany,
 * a automat układający zestaw bierze z jednej kategorii najwyżej jedno pytanie. Brak kategorii = „Bez kategorii”.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_templates', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('text')->index();
        });

        // Kategorie dla startowego banku pytań (pytania dopisane przez admina zostają „Bez kategorii”).
        foreach ([
            'Czy Lech strzeli gola w 1. połowie?' => 'Bramki',
            'Czy Lech wygra 1. połowę?' => 'Inne',
            'Czy w meczu padną więcej niż 2 gole?' => 'Bramki',
            'Czy obie drużyny strzelą gola?' => 'Bramki',
            'Czy Lech zachowa czyste konto?' => 'Bramkarz',
            'Czy w meczu będzie rzut karny?' => 'Stałe fragmenty',
            'Czy w meczu będzie czerwona kartka?' => 'Kartki',
            'Czy Lech strzeli gola po stałym fragmencie gry?' => 'Stałe fragmenty',
            'Czy Lech strzeli pierwszy?' => 'Bramki',
            'Czy padnie gol w ostatnich 15 minutach?' => 'Bramki',
            'Czy rezerwowy Lecha strzeli gola?' => 'Zmiany',
            'Czy Lech strzeli gola głową?' => 'Bramki',
            'Czy mecz zakończy się remisem do przerwy?' => 'Inne',
            'Czy sędzia pokaże więcej niż 4 żółte kartki?' => 'Kartki',
            'Czy Lech będzie miał więcej rzutów rożnych niż rywal?' => 'Rzuty rożne',
        ] as $text => $category) {
            DB::table('question_templates')->where('text', $text)->whereNull('category')->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('question_templates', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
