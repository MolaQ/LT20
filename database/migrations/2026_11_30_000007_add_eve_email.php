<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Przypomnienie e-mailem przed meczem (App\Support\EveMail): gracz wybiera, ile godzin przed pierwszym gwizdkiem
 * (users.email_reminder_hours, domyślnie 24, 0 = wyłączone), a wysłane maile są zapisane, żeby szły raz na kolejkę.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('email_reminder_hours')->default(24)->after('push_prefs');
        });
        Schema::create('matchday_email_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->unique(['matchday_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matchday_email_reminders');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_reminder_hours');
        });
    }
};
