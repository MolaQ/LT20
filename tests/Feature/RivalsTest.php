<?php

use App\Enums\Permission;
use App\Models\Rival;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Baza rywali w panelu: nazwa, skrót i barwy; barwy szukane po nazwie meczu bez względu na wielkość liter.

function rivalsAdmin(): User
{
    PermissionModel::findOrCreate(Permission::SeasonList->value, 'web');
    PermissionModel::findOrCreate(Permission::SeasonEdit->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo([Permission::SeasonList->value, Permission::SeasonEdit->value]);

    return $admin;
}

test('the migration adds GKS Katowice with its colours', function () {
    expect(Rival::colorsFor('gks katowice'))->toBe(['#0f8a43', '#facc15', '#111111'])
        ->and(Rival::colorsFor('Nieznany Klub'))->toBe([]);
});

test('an admin adds a rival with colours', function () {
    Livewire::actingAs(rivalsAdmin())->test('pages::dashboard.rivals')
        ->call('add')
        ->set('name', '  Legia   Warszawa ')
        ->set('shortName', 'LEG')
        ->set('colors', ['#FFFFFF', '#00843d', '#d00000'])
        ->call('save')
        ->assertHasNoErrors();

    $rival = Rival::where('name', 'Legia Warszawa')->firstOrFail();
    expect($rival->colors)->toBe(['#ffffff', '#00843d', '#d00000'])
        ->and(Rival::colorsFor('Legia Warszawa'))->toBe(['#ffffff', '#00843d', '#d00000']);
});

test('a wrong colour is refused', function () {
    Livewire::actingAs(rivalsAdmin())->test('pages::dashboard.rivals')
        ->call('add')
        ->set('name', 'Raków Częstochowa')
        ->set('colors', ['red'])
        ->call('save')
        ->assertHasErrors('colors.0');
});
