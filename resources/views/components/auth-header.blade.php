@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1 text-center">
    <x-ui.heading size="xl" level="1">{{ $title }}</x-ui.heading>
    <x-ui.text>{{ $description }}</x-ui.text>
</div>
