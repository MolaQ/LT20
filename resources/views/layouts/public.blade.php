{{--
    Strony publiczne w nowej skorupie aplikacji (wygląd 2.0), bez Flux (komponenty x-ui.*). Tryb legacy dokłada
    wyszukiwarkę w nagłówku, okna pojedynku i meczu Lecha oraz prawą kolumnę z rywalami i newsami.
--}}
@props(['title' => null])

<x-layouts::shell :title="$title" legacy>
    <x-slot:aside>
        <x-public.right-panel />
    </x-slot:aside>

    {{ $slot }}
</x-layouts::shell>
