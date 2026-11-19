<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackPageView;
use App\Models\BannerCampaign;
use App\Models\BannerStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Liczniki rotatora banerów: kliknięcie (przekierowanie na stronę partnera) i wyświetlenia
 * (rotator wysyła numery banerów, które były widoczne w co najmniej połowie przez sekundę).
 * Roboty nie są liczone.
 */
class BannerController extends Controller
{
    public function click(Request $request, BannerCampaign $campaign): RedirectResponse
    {
        if (! $this->isBot($request)) {
            BannerStat::bump($campaign->id, 'clicks');
        }

        return redirect()->away($campaign->url);
    }

    public function views(Request $request): Response
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:10'],
            'ids.*' => ['integer'],
        ]);

        if (! $this->isBot($request)) {
            BannerCampaign::live()->whereIn('id', array_unique($data['ids']))->pluck('id')
                ->each(fn (int $id) => BannerStat::bump($id, 'views'));
        }

        return response()->noContent();
    }

    private function isBot(Request $request): bool
    {
        return preg_match(TrackPageView::BOTS, (string) $request->userAgent()) === 1;
    }
}
