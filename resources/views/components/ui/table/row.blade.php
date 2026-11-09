@props(['key' => null])

<tr @if ($key !== null) wire:key="table-row-{{ $key }}" @endif {{ $attributes }}>{{ $slot }}</tr>
