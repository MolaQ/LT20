<?php

// Poradnik dla nowych graczy (/poradnik): strona publiczna z czterema częściami.

test('the beginner guide is public and shows all four parts', function () {
    $this->get(route('howto'))->assertOk()
        ->assertSee(__('Your tip and points'))
        ->assertSee(__('Squad and competitions'))
        ->assertSee(__('Questions and the bonus'))
        ->assertSee(__('Your first match'));
});
