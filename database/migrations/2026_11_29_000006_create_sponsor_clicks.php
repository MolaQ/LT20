<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dzienne liczniki kliknięć w blok partnera rozgrywek (przekierowanie /p/{sponsor}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('day', 10);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['sponsor_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_clicks');
    }
};
