<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotator banerów partnerów: kampanie (grafika, link, tekst, waga, daty) w miejscach strony
 * i dzienne liczniki wyświetleń oraz kliknięć (statystyki dla partnera pod prywatnym linkiem).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slot', 20)->index();
            $table->string('title', 80)->nullable();
            $table->string('url');
            $table->string('image_path');
            $table->unsignedTinyInteger('weight')->default(5);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('stats_token', 40)->unique();
            $table->timestamps();
        });

        Schema::create('banner_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('day', 10);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['banner_campaign_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_stats');
        Schema::dropIfExists('banner_campaigns');
    }
};
