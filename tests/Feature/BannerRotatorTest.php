<?php

use App\Enums\BannerSlot;
use App\Enums\Permission;
use App\Enums\SeasonStatus;
use App\Models\BannerCampaign;
use App\Models\BannerStat;
use App\Models\Season;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;

// Rotator banerów partnerów: kampanie z panelu, liczniki wyświetleń i kliknięć, statystyki partnera pod linkiem.

function bannerEditor(): User
{
    PermissionModel::findOrCreate(Permission::SeasonEdit->value, 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::SeasonEdit->value);

    return $admin;
}

function bannerCampaign(array $attributes = []): BannerCampaign
{
    return BannerCampaign::create(array_merge([
        'name' => 'KKSLech.com',
        'slot' => BannerSlot::Panel->value,
        'title' => 'Codziennie newsy o Lechu',
        'url' => 'https://kkslech.com',
        'image_path' => 'banners/test.jpg',
        'weight' => 5,
    ], $attributes));
}

test('an admin adds a partner banner and the right column shows it', function () {
    Storage::fake('public');
    Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);

    Livewire::actingAs(bannerEditor())->test('pages::dashboard.banners')
        ->call('openForm')
        ->set('name', 'KKSLech.com')
        ->set('place', BannerSlot::Panel->value)
        ->set('caption', 'Codziennie newsy o Lechu')
        ->set('url', 'https://kkslech.com')
        ->set('image', UploadedFile::fake()->image('baner.jpg', 600, 500))
        ->call('save')
        ->assertHasNoErrors();

    $campaign = BannerCampaign::sole();
    Storage::disk('public')->assertExists($campaign->image_path);
    expect($campaign->stats_token)->toHaveLength(40);

    $this->get(route('home'))->assertOk()
        ->assertSee('storage/'.$campaign->image_path, false)
        ->assertSee(route('banner.click', $campaign), false)
        ->assertSee('Codziennie newsy o Lechu');
});

test('a banner of the wrong size or without a link is rejected', function () {
    Storage::fake('public');

    Livewire::actingAs(bannerEditor())->test('pages::dashboard.banners')
        ->set('name', 'Partner')
        ->set('place', BannerSlot::Season->value)
        ->set('image', UploadedFile::fake()->image('baner.jpg', 600, 500))
        ->call('save')
        ->assertHasErrors(['image', 'url']);

    expect(BannerCampaign::count())->toBe(0);
});

test('a season bar banner rotates in the season bar', function () {
    $season = Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    $campaign = bannerCampaign(['slot' => BannerSlot::Season->value, 'image_path' => 'banners/szeroki.jpg']);

    $this->get(route('home'))->assertOk()
        ->assertSee('bannerRotator', false)
        ->assertSee('storage/banners/szeroki.jpg', false)
        ->assertSee(route('banner.click', $campaign), false);
});

test('switched off and ended banners are not shown', function () {
    Season::create(['number' => 1, 'status' => SeasonStatus::Active->value]);
    bannerCampaign(['is_active' => false, 'image_path' => 'banners/off.jpg']);
    bannerCampaign(['ends_on' => now()->subDay()->toDateString(), 'image_path' => 'banners/old.jpg']);
    bannerCampaign(['starts_on' => now()->addDay()->toDateString(), 'image_path' => 'banners/soon.jpg']);

    expect(BannerCampaign::forSlot(BannerSlot::Panel))->toBeEmpty();
    $this->get(route('home'))->assertOk()
        ->assertDontSee('banners/off.jpg', false)
        ->assertDontSee('banners/old.jpg', false)
        ->assertDontSee('banners/soon.jpg', false);
});

test('a click is counted and redirects to the partner', function () {
    $campaign = bannerCampaign();

    $this->get(route('banner.click', $campaign))->assertRedirect('https://kkslech.com');
    $this->get(route('banner.click', $campaign))->assertRedirect('https://kkslech.com');
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get(route('banner.click', $campaign))->assertRedirect('https://kkslech.com');

    expect(BannerStat::sole()->clicks)->toBe(2);
});

test('views are counted only for banners that are showing', function () {
    $live = bannerCampaign();
    $off = bannerCampaign(['is_active' => false]);

    $this->postJson(route('banner.views'), ['ids' => [$live->id, $off->id, $live->id]])->assertNoContent();
    $this->postJson(route('banner.views'), ['ids' => [$live->id]])->assertNoContent();

    expect(BannerStat::where('banner_campaign_id', $live->id)->value('views'))->toBe(2)
        ->and(BannerStat::where('banner_campaign_id', $off->id)->exists())->toBeFalse();
});

test('the partner sees the stats under the private link and a new link replaces the old one', function () {
    $campaign = bannerCampaign();
    BannerStat::create(['banner_campaign_id' => $campaign->id, 'day' => now()->toDateString(), 'views' => 1234, 'clicks' => 56]);
    $oldToken = $campaign->stats_token;

    $this->get(route('partner.stats', $oldToken))->assertOk()
        ->assertSee('KKSLech.com')
        ->assertSee('1 234')
        ->assertSee('4,5%');

    Livewire::actingAs(bannerEditor())->test('pages::dashboard.banners')->call('newToken', $campaign->id);

    $this->get(route('partner.stats', $oldToken))->assertNotFound();
    $this->get(route('partner.stats', $campaign->fresh()->stats_token))->assertOk();
});

test('a player without the permission cannot add banners', function () {
    PermissionModel::findOrCreate(Permission::SeasonList->value, 'web');
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SeasonList->value);

    Livewire::actingAs($user)->test('pages::dashboard.banners')->call('openForm')->assertForbidden();
});
