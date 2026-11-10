<?php

namespace App\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Ustawienia konta w dwóch miejscach: na stronie (/settings/..., układ strony) i w panelu admina
 * (/panel/settings/..., układ panelu, wejście z menu bocznego panelu). Układ i zakładki zależą od $inPanel,
 * ustawianego przy pierwszym wejściu (kolejne żądania Livewire nie mają już trasy strony).
 */
trait SettingsPage
{
    public bool $inPanel = false;

    public function mountSettingsPage(): void
    {
        $this->inPanel = request()->routeIs('dashboard.settings.*');
    }

    protected function settingsView(string $title): View
    {
        return $this->view()
            ->layout($this->inPanel ? 'layouts::admin' : 'layouts::public')
            ->title(__($title));
    }
}
