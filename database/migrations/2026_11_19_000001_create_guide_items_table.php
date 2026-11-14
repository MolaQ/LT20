<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Edytowalne „Zasady gry” i „FAQ” (14.11.2026): pozycje akordeonów z panelu admina (Treści → Zasady i FAQ).
 * Pusta tabela = domyślne teksty z App\Support\Guide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_items', function (Blueprint $table) {
            $table->id();
            // rules | faq
            $table->string('kind', 10)->index();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title', 200);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_items');
    }
};
