<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Admin ręcznie zmienia konto gracza w panelu użytkowników: nazwę, drużynę, e-mail i hasło.

function accountAdmin(): User
{
    Role::findOrCreate(RoleName::Admin->value, 'web');
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    return $admin;
}

test('an admin changes the name, team, email and password of a player', function () {
    $admin = accountAdmin();
    $user = User::factory()->create(['team_name' => 'Stara Drużyna', 'email' => 'stary@example.com']);

    Livewire::actingAs($admin)->test('pages::dashboard.users')
        ->call('editAccount', $user->id)
        ->assertSet('accountEmail', 'stary@example.com')
        ->set('accountName', 'Nowe Imię')
        ->set('accountTeam', '  Nowa   Drużyna ')
        ->set('accountEmail', 'Nowy@Example.com')
        ->set('accountPassword', 'NoweHaslo123!')
        ->call('saveAccount')
        ->assertHasNoErrors();

    $user = $user->fresh();
    expect($user->name)->toBe('Nowe Imię')
        ->and($user->team_name)->toBe('Nowa Drużyna')
        ->and($user->email)->toBe('nowy@example.com')
        ->and(Hash::check('NoweHaslo123!', $user->password))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $log = AuditLog::where('event', 'user.updated')->sole();
    expect(json_encode($log->toArray()))->not->toContain('NoweHaslo123!');
});

test('an empty password keeps the old one and a taken email or team is rejected', function () {
    $admin = accountAdmin();
    User::factory()->create(['team_name' => 'Zajęta', 'email' => 'zajety@example.com']);
    $user = User::factory()->create(['team_name' => 'Moja', 'password' => 'StareHaslo123!']);

    Livewire::actingAs($admin)->test('pages::dashboard.users')
        ->call('editAccount', $user->id)
        ->set('accountTeam', 'zajęta')
        ->set('accountEmail', 'zajety@example.com')
        ->call('saveAccount')
        ->assertHasErrors(['accountTeam', 'accountEmail']);

    Livewire::actingAs($admin)->test('pages::dashboard.users')
        ->call('editAccount', $user->id)
        ->set('accountName', 'Inna nazwa')
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect(Hash::check('StareHaslo123!', $user->fresh()->password))->toBeTrue();
});

test('switching off the confirmation makes the email unconfirmed', function () {
    $user = User::factory()->create(['team_name' => 'Drużyna']);

    Livewire::actingAs(accountAdmin())->test('pages::dashboard.users')
        ->call('editAccount', $user->id)
        ->set('accountVerified', false)
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('only an admin can edit accounts', function () {
    $user = User::factory()->create();

    Livewire::actingAs(User::factory()->create())->test('pages::dashboard.users')
        ->call('editAccount', $user->id)
        ->assertForbidden();
});
