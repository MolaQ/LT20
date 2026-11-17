<?php

use App\Support\Guide;

// Poradnik dla nowych graczy (/poradnik): strona publiczna z pięcioma częściami.

test('the beginner guide is public and shows all five parts', function () {
    $this->get(route('howto'))->assertOk()
        ->assertSee(__('Your tip and points'))
        ->assertSee(__('Squad and competitions'))
        ->assertSee(__('Experience and form'))
        ->assertSee(__('What experience decides'))
        ->assertSee(__('Questions and the bonus'))
        ->assertSee(__('Your first match'));
});

test('the rules explain experience and form', function () {
    $titles = array_column(Guide::rules(), 0);

    expect($titles)->toContain(e(__('Experience')))->toContain(e(__('Form')));
    $this->get(route('rules'))->assertOk()->assertSee(__('Experience'));
});
