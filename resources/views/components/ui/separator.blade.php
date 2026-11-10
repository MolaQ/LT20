{{-- Linia oddzielająca (zamiennik flux:separator), z text: napis na środku. --}}
@props(['text' => null, 'vertical' => false, 'variant' => null])

@if ($text)
    <div {{ $attributes->class('flex items-center gap-3 text-xs font-bold uppercase tracking-wider text-ui-muted') }}>
        <span class="h-px flex-1 bg-ui-line"></span>{{ $text }}<span class="h-px flex-1 bg-ui-line"></span>
    </div>
@else
    <div role="separator" {{ $attributes->class($vertical ? 'w-px self-stretch bg-ui-line' : 'h-px w-full bg-ui-line') }}></div>
@endif
