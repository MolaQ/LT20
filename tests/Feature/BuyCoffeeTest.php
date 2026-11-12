<?php

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

// Wpłaty przez buycoffee.to: przycisk na stronie wsparcia (bez Przelewy24) i wpis wpłaty w panelu z naliczeniem premium.

beforeEach(function () {
    config(['services.przelewy24.merchant_id' => 0, 'services.przelewy24.crc' => null, 'services.przelewy24.api_key' => null]);
    Role::findOrCreate(RoleName::Premium->value, 'web');
});

test('the support page links to buycoffee.to and asks for the account e-mail', function () {
    config(['services.buycoffee.url' => 'https://buycoffee.to/lechtyper']);
    $user = User::factory()->create(['email' => 'kibic@example.com']);

    $this->actingAs($user)->get(route('support'))->assertOk()
        ->assertSee('href="https://buycoffee.to/lechtyper"', false)
        ->assertSee('kibic@example.com')
        ->assertDontSee(__('Online payments are not available yet.'));
});

test('without a buycoffee.to address the page says that payments are not available', function () {
    config(['services.buycoffee.url' => 'https://example.com/obce']);

    $this->get(route('support'))->assertOk()
        ->assertDontSee('https://example.com/obce')
        ->assertSee(__('Online payments are not available yet.'));
});

test('an admin records a buycoffee.to payment and premium follows the price list', function () {
    Role::findOrCreate(RoleName::Admin->value, 'web');
    PermissionModel::findOrCreate(Permission::DashboardAccess->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::DashboardAccess->value);
    $admin->assignRole(RoleName::Admin->value);
    $player = User::factory()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.payments')
        ->call('openPayment')
        ->set('userId', $player->id)
        ->set('source', 'buycoffee')
        ->set('amount', '15')
        ->call('savePayment')
        ->assertHasNoErrors()
        ->assertSee('buycoffee.to');

    $payment = Payment::firstOrFail();
    expect($payment->source)->toBe('buycoffee')
        ->and($payment->status)->toBe(Payment::PAID)
        ->and($payment->premium_days)->toBe(30)
        ->and($player->fresh()->premium_until->isFuture())->toBeTrue();

    Livewire::actingAs($admin)->test('pages::dashboard.payments')
        ->call('openPayment')->set('userId', $player->id)->set('source', 'p24')->set('amount', '15')
        ->call('savePayment')->assertHasErrors('source');
});

test('with premium free for everyone every player has premium and the page offers only a coffee', function () {
    config(['premium.free' => true, 'services.buycoffee.url' => 'https://buycoffee.to/lechtyper']);
    $user = User::factory()->create();

    expect(\App\Support\Premium::isActive($user))->toBeTrue()
        ->and(\App\Support\Premium::isActive(null))->toBeFalse();

    $this->actingAs($user)->get(route('support'))->assertOk()
        ->assertSee(__('Buy us a coffee'))
        ->assertSee('href="https://buycoffee.to/lechtyper"', false)
        ->assertDontSee(__('Half a year'))
        ->assertDontSee(__('Online payments are not available yet.'));
});

test('with premium free for everyone a recorded coffee gives no premium days', function () {
    config(['premium.free' => true]);
    Role::findOrCreate(RoleName::Admin->value, 'web');
    PermissionModel::findOrCreate(Permission::DashboardAccess->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::DashboardAccess->value);
    $admin->assignRole(RoleName::Admin->value);
    $player = User::factory()->create();

    Livewire::actingAs($admin)->test('pages::dashboard.payments')
        ->call('openPayment')->set('userId', $player->id)->set('source', 'buycoffee')->set('amount', '20')
        ->call('savePayment')->assertHasNoErrors();

    expect(Payment::firstOrFail()->premium_days)->toBe(0)
        ->and($player->fresh()->premium_until)->toBeNull();
});
