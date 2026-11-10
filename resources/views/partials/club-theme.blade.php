{{--
    Kolory strony jako zmienne CSS: barwy klubu z config/club.php, zmienione w panelu „Kolory strony” (App\Support\SiteColors).
    Kolory jasnego motywu tylko dla :root:not(.dark), bo ciemny motyw ma własne (app.css).
--}}
@php
    $siteColors = \App\Support\SiteColors::all();
    $siteCss = \App\Support\SiteColors::css($siteColors);
@endphp
<style>
    :root { {{ $siteCss[':root'] }} }
    :root:not(.dark) { {{ $siteCss[':root:not(.dark)'] }} }
</style>
<meta name="theme-color" content="{{ $siteColors['deep'] }}">
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('club.brand.prefix').config('club.brand.suffix') }}">
