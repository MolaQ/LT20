<?php

namespace App\Jobs;

use App\Actions\Results\SaveMatchdayResult;
use App\Models\Matchday;
use App\Support\RecalcProgress;
use DomainException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Przeliczenie kolejki w tle (etap F przebudowy): SaveMatchdayResult::recalculate z paskiem postępu
 * (RecalcProgress). Przy wielu graczach strona admina nie czeka na koniec, tylko pokazuje postęp.
 * Na hostingu współdzielonym zadania wykonuje cron (routes/console.php, queue:work --stop-when-empty).
 */
class RecalculateMatchday implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    /** Jedna próba: przeliczenie jest w transakcji, a błąd (np. zła kolejność kolejek) trzeba pokazać adminowi. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $matchdayId) {}

    public function uniqueId(): string
    {
        return (string) $this->matchdayId;
    }

    public function handle(SaveMatchdayResult $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $matchday = Matchday::findOrFail($this->matchdayId);
        RecalcProgress::set($matchday->id, 5, __('Starting'));

        try {
            $stats = $action->recalculate($matchday, fn (int $percent, string $label) => RecalcProgress::set($matchday->id, $percent, $label));
        } catch (DomainException $e) {
            RecalcProgress::set($matchday->id, 100, __('Stopped'), 'failed', $e->getMessage());

            return;
        }

        $message = __('Matchday recalculated. Matches settled: :count.', ['count' => $stats['fixtures']]);
        if (($stats['swiss'] ?? 0) > 0) {
            $message .= ' '.__('Liga podwórkowa: the next round has been drawn (:count matches).', ['count' => $stats['swiss']]);
        }

        RecalcProgress::set($matchday->id, 100, __('Done'), 'done', $message);
    }

    public function failed(?Throwable $e): void
    {
        RecalcProgress::set($this->matchdayId, 100, __('Stopped'), 'failed', __('Recalculation failed. Details are in the logs.'));
    }
}
