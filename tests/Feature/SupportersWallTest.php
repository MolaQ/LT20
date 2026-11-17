<?php

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\SupporterRank;
use App\Models\User;
use App\Support\SupporterRanks;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

// Ściana wspierających i rangi (17.11.2026): suma wpłat z 12 miesięcy, kwoty ukryte, opcja ukrycia, wpłaty bez konta.

function supportPaid(?User $user, int $grosze, string $when = 'now'): Payment
{
    return Payment::create([
        'user_id' => $user?->id, 'kind' => Payment::KIND_SUPPORT, 'amount' => $grosze, 'status' => Payment::PAID,
        'source' => 'buycoffee', 'premium_days' => 0, 'paid_at' => now()->modify($when),
    ]);
}

test('the default ranks are kibol, patron and mecenas and the rank follows the 12 month total', function () {
    expect(SupporterRanks::all()->pluck('name')->all())->toBe(['Kibol', 'Patron', 'Mecenas']);

    $fan = User::factory()->create();
    supportPaid($fan, 1500);
    supportPaid($fan, 1000, '-2 months');
    supportPaid($fan, 50000, '-13 months');

    expect(SupporterRanks::forUser($fan->id)?->name)->toBe('Patron')
        ->and(SupporterRanks::forTotal(50))->toBeNull()
        ->and(SupporterRanks::forTotal(10000)?->name)->toBe('Mecenas');
});

test('the wall is sorted by the biggest total, hides amounts and shows anonymous coffees', function () {
    $small = User::factory()->create(['team_name' => 'Mała Kawka']);
    $big = User::factory()->create(['team_name' => 'Wielki Mecenas']);
    supportPaid($small, 500);
    supportPaid($big, 6000);
    supportPaid($big, 6000);
    supportPaid(null, 2500);

    $html = $this->get(route('support'))->assertOk()
        ->assertSeeInOrder(['Wielki Mecenas', __('Anonymous kibol'), 'Mała Kawka'])
        ->getContent();

    expect($html)->not->toContain('120 zł')->not->toContain('25 zł');
});

test('a supporter can hide from the wall and the team name loses the background', function () {
    $fan = User::factory()->create(['team_name' => 'Ukryty Kibol']);
    supportPaid($fan, 2000);

    $this->actingAs($fan)->get(route('support'))->assertOk()
        ->assertSee(__('Thank you, :rank! Your support keeps LechTYPER going.', ['rank' => 'Patron']));

    Livewire::actingAs($fan)->test('pages::support')->call('toggleWall');

    expect($fan->fresh()->show_as_supporter)->toBeFalse();
    Once::flush();
    expect(SupporterRanks::forUser($fan->id))->toBeNull()
        ->and(SupporterRanks::wall())->toBe([]);
});

test('an admin edits the ranks and records an anonymous coffee', function () {
    Role::findOrCreate(RoleName::Admin->value, 'web');
    PermissionModel::findOrCreate(Permission::DashboardAccess->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::DashboardAccess->value);
    $admin->assignRole(RoleName::Admin->value);

    $page = Livewire::actingAs($admin)->test('pages::dashboard.payments');
    $page->set('ranks.0.name', 'Kibol z Bułgarskiej')->set('ranks.2.threshold', '150')
        ->call('removeRank', 1)
        ->call('addRank')->set('ranks.2.name', 'Legenda')->set('ranks.2.threshold', '500')->set('ranks.2.color', '#ff0000')
        ->call('saveRanks')->assertHasNoErrors();

    expect(SupporterRank::orderBy('threshold')->get(['name', 'threshold', 'color'])->toArray())->toBe([
        ['name' => 'Kibol z Bułgarskiej', 'threshold' => 100, 'color' => '#3b82f6'],
        ['name' => 'Mecenas', 'threshold' => 15000, 'color' => '#f59e0b'],
        ['name' => 'Legenda', 'threshold' => 50000, 'color' => '#ff0000'],
    ]);

    $page->set('ranks.0.color', 'czerwony')->call('saveRanks')->assertHasErrors('ranks.0.color');

    // Kawka bez konta: tylko z buycoffee.to.
    $page->call('openPayment')->set('source', 'buycoffee')->set('amount', '10')->call('savePayment')->assertHasNoErrors();
    expect(Payment::whereNull('user_id')->count())->toBe(1);

    $page->call('openPayment')->set('source', 'manual')->set('amount', '10')->call('savePayment')->assertHasErrors('userId');
});

test('the team name gets the background in the colour of the rank', function () {
    $fan = User::factory()->create();
    supportPaid($fan, 10000);

    $html = Blade::render('<x-supporter-name :user-id="$id">Kolejorz</x-supporter-name>', ['id' => $fan->id]);
    $plain = Blade::render('<x-supporter-name :user-id="$id">Kolejorz</x-supporter-name>', ['id' => null]);

    expect($html)->toContain('supporter-name')->toContain('#f59e0b')->toContain(e(__(':rank of LechTYPER', ['rank' => 'Mecenas'])))
        ->and(trim($plain))->toBe('Kolejorz');
});
