{{--
    Nazwa drużyny z delikatnym tłem w kolorze rangi wspierającego (App\Support\SupporterRanks), bez rangi sama nazwa.
    Użycie: <x-supporter-name :user-id="$user->id">{{ $name }}</x-supporter-name>
--}}
@props(['userId' => null])
@php
    $supporterRank = \App\Support\SupporterRanks::forUser($userId ? (int) $userId : null);
@endphp
@if ($supporterRank)
    <span {{ $attributes->class('supporter-name') }} style="--sr: {{ $supporterRank->color }}" title="{{ __(':rank of LechTYPER', ['rank' => $supporterRank->name]) }}">{{ $slot }}</span>
@else
    {{ $slot }}
@endif
