{{-- Opcja listy x-ui.select. --}}
@props(['value' => null])

<option value="{{ $value }}" {{ $attributes }}>{{ $slot }}</option>
