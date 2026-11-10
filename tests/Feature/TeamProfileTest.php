<?php

use App\Models\TeamLike;
use App\Models\User;
use Livewire\Livewire;

// Klub użytkownika: wyświetlenia (raz na sesję, bez właściciela) i polubienia (niebieskie serduszko).

test('a team view is counted once per session and not for the owner', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();

    $this->actingAs($visitor)->get(route('team.show', $owner))->assertOk();
    $this->actingAs($visitor)->get(route('team.show', $owner))->assertOk();
    expect($owner->fresh()->profile_views)->toBe(1);

    $this->actingAs($owner)->get(route('team.show', $owner))->assertOk();
    expect($owner->fresh()->profile_views)->toBe(1);
});

test('a player can like a team and take the like back', function () {
    $owner = User::factory()->create();
    $fan = User::factory()->create();

    $page = Livewire::actingAs($fan)->test('pages::team-profile', ['user' => $owner]);

    $page->call('toggleLike');
    expect(TeamLike::where('user_id', $owner->id)->count())->toBe(1);

    $page->call('toggleLike');
    expect(TeamLike::where('user_id', $owner->id)->count())->toBe(0);
});

test('nobody can like their own team', function () {
    $owner = User::factory()->create();

    Livewire::actingAs($owner)->test('pages::team-profile', ['user' => $owner])
        ->call('toggleLike')
        ->assertForbidden();
});
