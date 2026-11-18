<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\Matchday;
use App\Models\User;
use App\Support\Graphic;
use App\Support\OgImage;
use App\Support\Seo;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obrazki rysowane na serwerze (App\Support\OgImage): podgląd linków do tabeli i profilu drużyny (og:image)
 * oraz grafika przed meczem do pobrania z panelu. Gotowe pliki leżą chwilę w storage/app/og, żeby robot
 * Facebooka i kolejne udostępnienia nie rysowały ich od nowa. Serwer bez GD oddaje obrazek domyślny.
 */
class OgImageController extends Controller
{
    /** Ważność gotowego pliku w sekundach. */
    private const TTL = 1800;

    public function table(Competition $competition, int $round): Response|RedirectResponse
    {
        return $this->png('table-'.$competition->id.'-'.max(1, $round), fn () => OgImage::table($competition, $round));
    }

    public function team(User $user): Response|RedirectResponse
    {
        return $this->png('team-'.$user->id, fn () => OgImage::team($user));
    }

    /** Grafika „przed meczem” do pobrania (panel kolejek): post albo relacja. */
    public function matchday(Matchday $matchday, string $format): Response|RedirectResponse
    {
        $format = $format === 'story' ? 'story' : 'post';
        if (! Graphic::available()) {
            return redirect()->back()->with('error', __('The server cannot draw images (missing GD).'));
        }

        $name = Str::slug('lech-'.($matchday->opponent ?: 'kolejka-'.$matchday->number).'-'.($format === 'story' ? 'relacja' : 'post')).'.png';

        return response(OgImage::matchday($matchday, $format), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @param  Closure(): string  $draw */
    private function png(string $key, Closure $draw): Response|RedirectResponse
    {
        if (! Graphic::available()) {
            return redirect(asset(Seo::DEFAULT_IMAGE));
        }

        $disk = Storage::disk('local');
        $path = 'og/'.$key.'.png';
        if (! $disk->exists($path) || $disk->lastModified($path) < now()->subSeconds(self::TTL)->getTimestamp()) {
            $disk->put($path, $draw());
        }

        return response((string) $disk->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.self::TTL,
        ]);
    }
}
