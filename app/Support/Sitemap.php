<?php

namespace App\Support;

use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Mapa strony dla wyszukiwarek (sitemap.xml) i robots.txt: strony publiczne, opublikowane newsy i kluby graczy.
 * Panel, ustawienia, logowanie i żądania Livewire są wyłączone z indeksowania. Mapa w cache na godzinę.
 */
final class Sitemap
{
    public static function robots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Disallow: /panel',
            'Disallow: /dashboard',
            'Disallow: /settings',
            'Disallow: /livewire',
            'Disallow: /login',
            'Disallow: /register',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);
    }

    public static function xml(): string
    {
        return Cache::remember('sitemap.xml', 3600, function () {
            $urls = [];
            foreach (['home' => 'daily', 'results' => 'daily', 'hall-of-fame' => 'weekly', 'history' => 'weekly', 'stars' => 'weekly',
                'rules' => 'monthly', 'faq' => 'monthly', 'support' => 'monthly', 'system' => 'daily', 'office' => 'weekly'] as $route => $freq) {
                $urls[] = [route($route), null, $freq];
            }
            foreach (News::published()->latest('published_at')->limit(500)->get(['id', 'title', 'updated_at']) as $news) {
                $urls[] = [route('news.show', $news), $news->updated_at, 'monthly'];
            }
            foreach (User::whereNotNull('team_name')->whereHas('roles')->orderBy('id')->limit(5000)->get(['id', 'updated_at']) as $user) {
                $urls[] = [route('team.show', $user), null, 'weekly'];
            }

            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as [$loc, $modified, $freq]) {
                $xml .= '  <url><loc>'.e($loc).'</loc>'
                    .($modified ? '<lastmod>'.$modified->toAtomString().'</lastmod>' : '')
                    .'<changefreq>'.$freq.'</changefreq></url>'."\n";
            }

            return $xml.'</urlset>'."\n";
        });
    }
}
