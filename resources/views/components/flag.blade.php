{{-- Flaga języka jako SVG (emoji flag nie działają na Windowsie). code: pl, en. --}}
@props(['code'])

@php
    $id = 'flag-'.uniqid();
@endphp
@if ($code === 'pl')
    <svg viewBox="0 0 16 10" aria-hidden="true" {{ $attributes->class('h-3.5 w-5 shrink-0 rounded-[2px] ring-1 ring-black/10') }}>
        <rect width="16" height="5" fill="#fff" /><rect y="5" width="16" height="5" fill="#dc143c" />
    </svg>
@elseif ($code === 'en')
    <svg viewBox="0 0 60 30" aria-hidden="true" {{ $attributes->class('h-3.5 w-5 shrink-0 rounded-[2px] ring-1 ring-black/10') }}>
        <clipPath id="{{ $id }}-s"><path d="M0,0 v30 h60 v-30 z" /></clipPath>
        <clipPath id="{{ $id }}-t"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z" /></clipPath>
        <g clip-path="url(#{{ $id }}-s)">
            <path d="M0,0 v30 h60 v-30 z" fill="#012169" />
            <path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6" />
            <path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#{{ $id }}-t)" stroke="#C8102E" stroke-width="4" />
            <path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10" />
            <path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6" />
        </g>
    </svg>
@endif
