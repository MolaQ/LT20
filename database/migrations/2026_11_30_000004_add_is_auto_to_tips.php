<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typ 0:0 zapisany automatycznie przy otwarciu strony typowania (tylko najbliższa kolejka). Gdy gracz zmieni typ,
 * flaga znika. Dzięki niej automatyczny typ nie wysyła rywalowi powiadomienia „już typował”.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tips', function (Blueprint $table) {
            $table->boolean('is_auto')->default(false)->after('save_order');
        });
    }

    public function down(): void
    {
        Schema::table('tips', function (Blueprint $table) {
            $table->dropColumn('is_auto');
        });
    }
};
