<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackPageView;
use App\Models\Sponsor;
use App\Models\SponsorClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kliknięcie w blok partnera rozgrywek: liczy kliknięcie (bez robotów) i przekierowuje na stronę partnera.
 */
class SponsorController extends Controller
{
    public function click(Request $request, Sponsor $sponsor): RedirectResponse
    {
        abort_unless(filled($sponsor->url), 404);

        if (preg_match(TrackPageView::BOTS, (string) $request->userAgent()) !== 1) {
            SponsorClick::bump($sponsor->id);
        }

        return redirect()->away((string) $sponsor->url);
    }
}
