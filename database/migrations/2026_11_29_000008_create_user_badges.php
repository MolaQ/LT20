<?php

use App\Support\Badges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odznaki graczy (App\Support\Badges): postęp i zdobyty stopień każdej odznaki. Po utworzeniu tabeli od razu
 * liczymy odznaki wstecz ze wszystkich rozegranych sezonów, żeby gracze startowali z tym, co już osiągnęli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 40);
            // Postęp: liczba trafień, najlepszy wynik sezonu albo długość serii (zależnie od odznaki).
            $table->unsignedInteger('progress')->default(0);
            // Zdobyty stopień: 0 = jeszcze nie, 1 = brąz, 2 = srebro, 3 = złoto (odznaki jednorazowe: 1).
            $table->unsignedTinyInteger('tier')->default(0);
            // Kiedy i w której kolejce stopień ostatnio wzrósł (grafika „Odznaki kolejki”); null przy liczeniu wstecz.
            $table->timestamp('earned_at')->nullable();
            $table->foreignId('matchday_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'badge']);
        });

        Badges::rebuild();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
