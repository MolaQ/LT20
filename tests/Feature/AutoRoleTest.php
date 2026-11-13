<?php

use App\Enums\RoleName;
use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\User;
use App\Support\Players;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

// Konto po potwierdzeniu adresu e-mail samo dostaje rolę User i trafia na listę sezonu (bez ręcznego zatwierdzania).

beforeEach(function () {
    Role::findOrCreate(RoleName::User->value, 'web');
    Role::findOrCreate(RoleName::Banned->value, 'web');
});

test('confirming the email address gives the User role and a place on the season list', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    SeasonTeam::create(['season_id' => $season->id, 'position' => 1]);
    $user = User::factory()->unverified()->create();

    expect(Players::canPlay($user))->toBeFalse();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($url);

    $user = $user->fresh();
    expect($user->hasRole(RoleName::User->value))->toBeTrue()
        ->and(SeasonTeam::where('season_id', $season->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('an account that already has a role keeps it', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Banned->value);

    expect(Players::approveVerified($user))->toBeFalse()
        ->and($user->fresh()->hasRole(RoleName::User->value))->toBeFalse();
});
