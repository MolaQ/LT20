{{--
    Strony publiczne w nowej skorupie aplikacji (wygląd 2.0). Treść stron wciąż korzysta z Flux,
    więc skorupa działa w trybie legacy, dopóki strony nie przejdą na komponenty x-ui.*.
--}}
@props(['title' => null])

<x-layouts::shell :title="$title" legacy>
    <x-slot:aside>
        <x-public.right-panel />
    </x-slot:aside>

    {{ $slot }}
</x-layouts::shell>
