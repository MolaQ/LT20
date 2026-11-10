<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Powiadomienia push: subskrypcje przeglądarek (format pakietu webpush), wybór rodzajów u gracza
    // i znaczniki wysłania na kolejce (żeby „typowanie otwarte” i przypomnienie poszły tylko raz).
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('push_prefs')->nullable()->after('locale');
        });

        Schema::table('matchdays', function (Blueprint $table) {
            $table->timestamp('push_open_at')->nullable();
            $table->timestamp('push_reminder_at')->nullable();
            $table->timestamp('push_results_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->dropColumn(['push_open_at', 'push_reminder_at', 'push_results_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_prefs');
        });

        Schema::dropIfExists('push_subscriptions');
    }
};
