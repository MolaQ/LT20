<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
    }

    public function down(): void
    {
        Schema::table('question_templates', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
