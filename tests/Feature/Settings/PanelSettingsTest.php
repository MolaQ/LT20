<?php

use App\Enums\Permission;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;

// Ustawienia konta otwarte z panelu admina zostają w układzie panelu (trasy dashboard.settings.*).

test('settings open inside the dashboard for users with dashboard access', function () {
    PermissionModel::findOrCreate(Permission::DashboardAccess->value, 'web');
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::DashboardAccess->value);

    $this->actingAs($user)->get(route('dashboard.settings.security'))
        ->assertOk()
        ->assertSee(route('dashboard.settings.profile'), false);
});

test('players without dashboard access cannot open the dashboard settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard.settings.profile'))->assertRedirect(route('home'));
});
