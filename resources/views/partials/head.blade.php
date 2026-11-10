<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

<title>
    {{ filled($title ?? null) && $title !== config('app.name') ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Motyw przed pierwszym malowaniem (bez mrugnięcia): zapisany wybór albo ustawienie telefonu, ten sam klucz co w app.js. --}}
<script>
    (() => {
        const apply = () => {
            let mode = 'system';
            try { mode = localStorage.getItem('flux.appearance') ?? 'system'; } catch (e) {}
            const dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        };
        apply();
        document.addEventListener('livewire:navigated', apply);
    })();
</script>
