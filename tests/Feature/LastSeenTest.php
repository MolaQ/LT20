<?php

use App\Models\User;

// Ostatnia aktywność konta: zapis przy wejściu, najwyżej raz na kilka minut.

test('a visit records when the user was last seen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))->assertOk();

    expect($user->fresh()->last_seen_at)->not->toBeNull();
});

test('a recent visit is not written again', function () {
    $user = User::factory()->create();
    $recent = now()->subMinute()->startOfSecond();
    $user->forceFill(['last_seen_at' => $recent])->save();

    $this->actingAs($user)->get(route('home'))->assertOk();

    expect($user->fresh()->last_seen_at->equalTo($recent))->toBeTrue();
});
