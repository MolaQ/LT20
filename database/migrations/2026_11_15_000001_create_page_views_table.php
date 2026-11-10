<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odsłony stron publicznych (App\Http\Middleware\TrackPageView, wzór z jokerpila.pl): bez IP i bez ciasteczek.
 * visitor_hash zmienia się codziennie, więc liczy unikalnych odwiedzających w dniu, ale nikogo nie śledzi dłużej.
 * Wpisy starsze niż PageView::RETENTION_DAYS usuwa codzienne model:prune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('route_name', 100)->nullable();
            $table->char('visitor_hash', 64);
            $table->string('referrer_host', 120)->nullable();
            $table->string('device', 8)->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['visitor_hash', 'created_at']);
            $table->index(['path', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
