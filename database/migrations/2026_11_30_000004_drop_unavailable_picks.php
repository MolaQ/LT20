<?php

use App\Support\Squad;
use Illuminate\Database\Migrations\Migration;

/**
 * Jednorazowo: zdejmuje ze składów kolejek przed gwizdkiem zawodników, którzy już mają status „Niedostępny”
 * (dalej robi to sama zmiana statusu w kadrze Lecha).
 */
return new class extends Migration
{
    public function up(): void
    {
        Squad::dropUnavailable();
    }

    public function down(): void
    {
        // Zdjętych wyborów nie da się odtworzyć.
    }
};
