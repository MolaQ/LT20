{{-- Nagłówek części poradnika (/poradnik): duży numer, tytuł i wstęp. --}}
@props(['number', 'title', 'lead' => null])

<div class="flex items-start gap-4">
    <span class="lech-bar grid size-12 shrink-0 place-items-center rounded-2xl font-display text-2xl font-bold text-white shadow-md">{{ $number }}</span>
    <div class="min-w-0">
        <h2 class="font-display text-xl font-semibold sm:text-2xl">{{ $title }}</h2>
        @if (filled($lead))
            <p class="mt-1 max-w-3xl text-ui-muted">{{ $lead }}</p>
        @endif
    </div>
</div>
