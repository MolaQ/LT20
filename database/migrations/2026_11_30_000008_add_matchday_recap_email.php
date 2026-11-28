<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail z podsumowaniem kolejki (App\Support\RecapMail): znacznik wysyłki na kolejce, żeby poszedł raz,
 * i zgoda gracza (domyślnie tak, wyłącza się w ustawieniach powiadomień albo linkiem z maila).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->timestamp('recap_email_at')->nullable()->after('push_results_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_recap')->default(true)->after('email_reminder_hours');
        });
    }

    public function down(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->dropColumn('recap_email_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_recap');
        });
    }
};
