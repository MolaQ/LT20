<?php

use App\Support\Squad;
use Illuminate\Database\Migrations\Migration;

/**
 * Jednorazowo: zdejmuje ze składów kolejek przed gwizdkiem zawodników, którzy już mają status „Niedostępny”
 * albo odpoczywają w chwili gwizdka (dalej robi to zmiana statusu w kadrze Lecha i zapis raportu meczu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Squad::dropUnavailable();
        Squad::dropResting();
    }

    public function down(): void
    {
        // Zdjętych wyborów nie da się odtworzyć.
    }
};
