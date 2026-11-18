<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rangi wspierających (17.11.2026): nazwa, próg sumy wpłat z 12 miesięcy (grosze) i kolor tła przy nazwie drużyny.
 * Gracz może się ukryć na ścianie wspierających (users.show_as_supporter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supporter_ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->unsignedInteger('threshold');
            $table->string('color', 7);
            $table->timestamps();
        });

        $now = now();
        DB::table('supporter_ranks')->insert([
            ['name' => 'Kibol', 'threshold' => 100, 'color' => '#3b82f6', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Patron', 'threshold' => 2000, 'color' => '#94a3b8', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mecenas', 'threshold' => 10000, 'color' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_as_supporter')->default(true)->after('premium_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_as_supporter');
        });
        Schema::dropIfExists('supporter_ranks');
    }
};
