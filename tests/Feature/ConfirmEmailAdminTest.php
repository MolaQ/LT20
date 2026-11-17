<?php

use App\Enums\RoleName;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Admin potwierdza adres e-mail bez kodu w panelu użytkowników.

test('an admin confirms an email without a code', function () {
    Role::findOrCreate(RoleName::Admin->value, 'web');
    Role::findOrCreate(RoleName::User->value, 'web');
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $user = User::factory()->unverified()->create(['verification_code' => '123456', 'verification_code_expires_at' => now()->subHour()]);

    Livewire::actingAs($admin)->test('pages::dashboard.users')
        ->assertSee('wire:confirm', false)
        ->call('confirmEmail', $user->id)
        ->assertDispatched('ui-toast');

    $user = $user->fresh();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->verification_code)->toBeNull()
        ->and($user->hasRole(RoleName::User->value))->toBeTrue();
});
