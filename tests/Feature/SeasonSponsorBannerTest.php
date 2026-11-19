<?php

use App\Enums\Permission;
use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Baner sponsora sezonu: szeroka grafika z panelu „Sezony” jako tło paska sezonu na stronie głównej.

function seasonEditor(): User
{
    PermissionModel::findOrCreate(Permission::SeasonEdit->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::SeasonEdit->value);

    return $admin;
}

test('an admin uploads a sponsor banner and the home page shows it', function () {
    Storage::fake('public');
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    Livewire::actingAs(seasonEditor())->test('pages::dashboard.seasons')
        ->call('edit', $season->id)
        ->set('banner', UploadedFile::fake()->image('baner.jpg', 1920, 400))
        ->set('sponsorName', 'KKSLech.com')
        ->set('sponsorLabel', 'Patron medialny')
        ->call('save')
        ->assertHasNoErrors();

    $path = $season->fresh()->sponsor_banner_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->get(route('home'))->assertOk()
        ->assertSee('storage/'.$path, false)
        ->assertSee('Patron medialny')
        ->assertSee('KKSLech.com');
});

test('a too narrow banner is rejected and removing the banner deletes the file', function () {
    Storage::fake('public');
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    Livewire::actingAs(seasonEditor())->test('pages::dashboard.seasons')
        ->call('edit', $season->id)
        ->set('banner', UploadedFile::fake()->image('maly.png', 800, 200))
        ->assertHasErrors('banner');

    $component = Livewire::actingAs(seasonEditor())->test('pages::dashboard.seasons')
        ->call('edit', $season->id)
        ->set('banner', UploadedFile::fake()->image('baner.png', 1600, 400))
        ->call('save');
    $path = $season->fresh()->sponsor_banner_path;

    $component->call('edit', $season->id)->call('clearBanner')->call('save')->assertHasNoErrors();

    expect($season->fresh()->sponsor_banner_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('a competition sponsor gets its own label', function () {
    Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    Livewire::actingAs(seasonEditor())->test('pages::dashboard.sponsors')
        ->call('openForm')
        ->set('name', 'Pizzeria')
        ->set('label', 'Sponsor ligi')
        ->call('save')
        ->assertHasNoErrors();

    expect(Sponsor::firstOrFail()->label)->toBe('Sponsor ligi');
});
