<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kiedy gracz zajął miejsce na liście sezonu. Podgląd typów liczy w kolejce tylko graczy, którzy byli w grze przed
 * jej pierwszym gwizdkiem, a grafika „Nowi gracze” pokazuje, kto dołączył między kolejkami.
 * Istniejące miejsca graczy dostają datę potwierdzenia maila (wtedy konto dostaje rolę i trafia na listę),
 * a bez niej datę rejestracji.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_teams', function (Blueprint $table) {
            $table->timestamp('joined_at')->nullable()->after('user_id');
        });

        DB::table('season_teams')
            ->join('users', 'users.id', '=', 'season_teams.user_id')
            ->select('season_teams.id', 'users.email_verified_at', 'users.created_at')
            ->orderBy('season_teams.id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('season_teams')->where('id', $row->id)->update(['joined_at' => $row->email_verified_at ?? $row->created_at]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('season_teams', function (Blueprint $table) {
            $table->dropColumn('joined_at');
        });
    }
};
