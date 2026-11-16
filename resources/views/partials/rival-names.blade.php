{{-- Podpowiedzi nazw rywali z bazy (panel Rywale) dla pola „Rywal”: ta sama nazwa = barwy na tablicy meczu. --}}
<datalist id="rival-names">
    @foreach (\App\Models\Rival::orderBy('name')->pluck('name') as $rivalName)
        <option value="{{ $rivalName }}"></option>
    @endforeach
</datalist>
