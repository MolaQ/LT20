<?php

use App\Enums\Permission;
use App\Models\GuideItem;
use App\Models\User;
use App\Support\Guide;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Zasady gry i FAQ edytowane w panelu (Treści → Zasady i FAQ): kopia tekstów domyślnych, edycja, kolejność, przywracanie.

function guideAdmin(): User
{
    PermissionModel::findOrCreate(Permission::NewsCreate->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::NewsCreate->value);

    return $admin;
}

test('without edits the pages show the default texts', function () {
    expect(Guide::faq())->toBe(Guide::defaults('faq'))
        ->and(Guide::rules())->toBe(Guide::defaults('rules'));
});

test('an admin edits an FAQ item and the FAQ page shows it', function () {
    $component = Livewire::actingAs(guideAdmin())->test('pages::dashboard.guide')->set('kind', 'faq');

    $first = GuideItem::where('kind', 'faq')->orderBy('position')->firstOrFail();
    expect(GuideItem::where('kind', 'faq')->count())->toBe(count(Guide::defaults('faq')));

    $component->call('edit', $first->id)
        ->set('title', 'Czy mogę typować z telefonu?')
        ->set('body', "Tak.\n\nStrona działa na telefonie.")
        ->call('save')
        ->assertHasNoErrors();

    expect(Guide::faq()[0])->toBe(['Czy mogę typować z telefonu?', '<p>Tak.</p><p>Strona działa na telefonie.</p>']);
    $this->get(route('faq'))->assertOk()->assertSee('Czy mogę typować z telefonu?');
});

test('an admin adds, moves and deletes rules and restores the defaults', function () {
    $component = Livewire::actingAs(guideAdmin())->test('pages::dashboard.guide');
    $count = count(Guide::defaults('rules'));

    $component->call('add')->set('title', 'Nowa zasada')->set('body', 'Treść')->call('save')->assertHasNoErrors();
    $new = GuideItem::where('title', 'Nowa zasada')->firstOrFail();
    expect(collect(Guide::rules())->last()[0])->toBe('Nowa zasada');

    $component->call('move', $new->id, -1);
    expect(Guide::rules()[$count - 1][0])->toBe('Nowa zasada');

    $component->call('delete', $new->id);
    expect(GuideItem::where('kind', 'rules')->count())->toBe($count);

    $component->call('restoreDefaults');
    expect(array_column(Guide::rules(), 0))->toBe(array_column(Guide::defaults('rules'), 0));
});

test('the panel needs the news permission', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard.guide'))->assertForbidden();
});
