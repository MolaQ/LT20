{{-- Tabela panelu (zamiennik flux:table). paginate: paginator Laravela, pod tabelą pojawiają się strony. --}}
@props(['paginate' => null])

<div {{ $attributes->class('w-full') }}>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">{{ $slot }}</table>
    </div>
    @if ($paginate && method_exists($paginate, 'links') && $paginate->hasPages())
        <div class="mt-4">{{ $paginate->links() }}</div>
    @endif
</div>
