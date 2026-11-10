<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/** Manifest PWA z konfiguracji klubu: nazwa, barwy i ikony (instalacja serwisu na ekranie telefonu). */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $name = config('club.brand.prefix').config('club.brand.suffix');

        return response()->json([
            'name' => $name,
            'short_name' => $name,
            'description' => __('Predictor game for :club fans', ['club' => config('club.name')]),
            'lang' => app()->getLocale(),
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => config('club.colors.deep'),
            'theme_color' => config('club.colors.deep'),
            'icons' => [
                ['src' => asset('images/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('images/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('images/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => __('Make a tip'), 'url' => route('tips', absolute: false)],
                ['name' => __('Results'), 'url' => route('results', absolute: false)],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
