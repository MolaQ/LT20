<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kampania (18.11.2026): skąd przyszło nowe konto (Facebook, Google, link zapraszający…), kto je zaprosił
 * i ile nagród premium zaproszający już dostał (App\Support\Referrals).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signup_source', 60)->nullable()->after('team_name');
            $table->foreignId('referred_by')->nullable()->after('signup_source')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('referral_rewards')->default(0)->after('referred_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn(['signup_source', 'referral_rewards']);
        });
    }
};
