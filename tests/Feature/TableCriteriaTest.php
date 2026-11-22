<?php

// Okno „Kolejność w tabeli” i przycisk „i” pod tabelami (kryteria z App\Support\Standings).

test('the criteria window lists every tie-breaker in order', function () {
    $html = (string) $this->blade('<x-table-criteria />');

    $order = [__('Points'), __('Goal balance'), __('Goals scored'), __('Exact tips'), __('Outcomes hit'), __('Bonuses'), __('Red cards'), __('Yellow cards'), __('Pre-season list')];
    $positions = array_map(fn (string $name) => strpos($html, e($name)), $order);

    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());
});

test('the info button opens the criteria window and is hidden on shared images', function () {
    $html = (string) $this->blade('<x-table-criteria-button />');

    expect($html)->toContain("name: 'table-criteria'")
        ->and($html)->toContain('data-share-hide');
});
