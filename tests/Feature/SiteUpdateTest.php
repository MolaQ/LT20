<?php

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\SiteUpdate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

// Aktualizacja strony z panelu: wgranie paczki w kawałkach, sprawdzenie, flaga z sumą SHA-256 i rozpakowanie przez cron.

/**
 * Buduje zip z podanymi wpisami (nazwa => treść) i zwraca jego ścieżkę.
 *
 * @param  array<string, string>  $entries
 */
function siteUpdateZip(array $entries): string
{
    $path = SiteUpdate::dir().'/zrodlo-'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();

    return $path;
}

/**
 * Wysyła plik do panelu w kawałkach, tak jak robi to przeglądarka.
 */
function siteUpdateSend($test, string $path, int $pieces = 3)
{
    $bytes = (string) file_get_contents($path);
    $size = (int) ceil(strlen($bytes) / $pieces);
    $response = null;

    foreach (str_split($bytes, $size) as $index => $piece) {
        $response = $test->post(route('dashboard.site-update.chunk'), [
            'upload' => 'testupload1',
            'index' => $index,
            'total' => $pieces,
            'chunk' => UploadedFile::fake()->createWithContent('czesc.bin', $piece),
        ], ['Accept' => 'application/json']);
    }

    return $response;
}

function siteUpdatePanelUser(bool $admin): User
{
    PermissionModel::findOrCreate(Permission::DashboardAccess->value, 'web');
    $user = User::factory()->create(['password' => bcrypt('tajne-haslo')]);
    $user->givePermissionTo(Permission::DashboardAccess->value);

    if ($admin) {
        Role::findOrCreate(RoleName::Admin->value, 'web');
        $user->assignRole(RoleName::Admin->value);
    }

    return $user;
}

beforeEach(function () {
    SiteUpdate::$dirOverride = sys_get_temp_dir().'/typer-aktualizacja-'.uniqid();
    File::ensureDirectoryExists(SiteUpdate::dir());
    $this->admin = siteUpdatePanelUser(admin: true);
    $this->valid = [
        'artisan' => '<?php', 'public/index.php' => '<?php', 'vendor/autoload.php' => '<?php', 'bootstrap/wersja-paczki.txt' => '2099-01-01 10:00:00',
        'database/migrations/2099_01_01_000000_nowa_tabela.php' => '<?php', '.env' => 'APP_KEY=zle', 'storage/logs/x.log' => 'x', 'public/storage/logo.png' => 'x',
    ];
});

afterEach(function () {
    File::deleteDirectory(SiteUpdate::dir());
    SiteUpdate::$dirOverride = null;
});

test('only an admin can open the update screen and send a package', function () {
    $editor = siteUpdatePanelUser(admin: false);

    $this->actingAs($editor)->get(route('dashboard.site-update'))->assertRedirect(route('dashboard'));
    siteUpdateSend($this->actingAs($editor), siteUpdateZip($this->valid))->assertForbidden();
    expect(SiteUpdate::package())->toBeNull();

    // Skrypt wgrywania musi dostać gotowe teksty i adres (nie surowe dyrektywy Blade), inaczej wybór pliku nic nie robi.
    $this->actingAs($this->admin)->get(route('dashboard.site-update'))->assertOk()->assertSee('typer-kod.zip')
        ->assertDontSee('@js(', false)->assertSee(str_replace('/', '\/', route('dashboard.site-update.chunk')), false);
});

test('an uploaded package is checked and waits for a password confirmed install', function () {
    siteUpdateSend($this->actingAs($this->admin), siteUpdateZip($this->valid))->assertOk()->assertJson(['done' => true]);

    $package = SiteUpdate::package();
    expect($package['wersja'])->toBe('2099-01-01 10:00:00')
        ->and($package['migracje'])->toBe(['2099_01_01_000000_nowa_tabela.php'])
        ->and($package['sha256'])->toBe(hash_file('sha256', SiteUpdate::zipPath()))
        ->and(SiteUpdate::flag())->toBeNull();

    Livewire::actingAs($this->admin)->test('pages::dashboard.site-update')
        ->assertSee('2099_01_01_000000_nowa_tabela.php')
        ->set('password', 'zle-haslo')->call('install')->assertHasErrors('password');
    expect(SiteUpdate::flag())->toBeNull();

    Livewire::actingAs($this->admin)->test('pages::dashboard.site-update')
        ->set('password', 'tajne-haslo')->call('install')->assertHasNoErrors()
        ->assertSee('2099-01-01 10:00:00');

    expect(SiteUpdate::flag())->toMatchArray(['typ' => 'instaluj', 'wersja' => '2099-01-01 10:00:00', 'sha256' => $package['sha256']]);
});

test('a file that is not a site package is rejected and removed', function () {
    siteUpdateSend($this->actingAs($this->admin), siteUpdateZip(['readme.txt' => 'x']))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => __('This is not a site package: the file :file is missing. Build it with paczka-na-serwer.bat.', ['file' => 'artisan'])]);
    expect(is_file(SiteUpdate::zipPath()))->toBeFalse();

    $nested = collect($this->valid)->mapWithKeys(fn ($content, $name) => ['typer/'.$name => $content])->all();
    $response = siteUpdateSend($this->actingAs($this->admin), siteUpdateZip($nested))->assertStatus(422);
    expect($response->json('message'))->toContain('(typer)');
});

test('a package zipped with a leading dot folder is accepted', function () {
    $dotted = collect($this->valid)->mapWithKeys(fn ($content, $name) => ['./'.$name => $content])->all();

    siteUpdateSend($this->actingAs($this->admin), siteUpdateZip($dotted))->assertOk()->assertJson(['done' => true]);
    expect(SiteUpdate::package()['wersja'])->toBe('2099-01-01 10:00:00');
});

test('a package older than the installed one needs an explicit consent', function () {
    $version = base_path('bootstrap/wersja-paczki.txt');
    $previous = is_file($version) ? file_get_contents($version) : null;
    File::put($version, '2026-11-12 09:00:00');

    try {
        siteUpdateSend($this->actingAs($this->admin), siteUpdateZip(['bootstrap/wersja-paczki.txt' => '2000-01-01 10:00:00'] + $this->valid))->assertOk();

        Livewire::actingAs($this->admin)->test('pages::dashboard.site-update')
            ->set('password', 'tajne-haslo')->call('install')->assertHasErrors('allowOlder');
        expect(SiteUpdate::flag())->toBeNull();

        Livewire::actingAs($this->admin)->test('pages::dashboard.site-update')
            ->set('password', 'tajne-haslo')->set('allowOlder', true)->call('install')->assertHasNoErrors();
        expect(SiteUpdate::flag())->not->toBeNull();
    } finally {
        $previous === null ? File::delete($version) : File::put($version, $previous);
    }
});

test('the unpack script refuses a changed package and never extracts settings, uploads or logs', function () {
    siteUpdateSend($this->actingAs($this->admin), siteUpdateZip($this->valid))->assertOk();
    Livewire::actingAs($this->admin)->test('pages::dashboard.site-update')->set('password', 'tajne-haslo')->call('install');
    $script = base_path('narzedzia/rozpakuj-paczke.php');

    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(SiteUpdate::dir()), $output, $code);
    $new = SiteUpdate::dir().'/nowa';

    expect($code)->toBe(0)
        ->and(is_file($new.'/artisan'))->toBeTrue()
        ->and(is_file($new.'/database/migrations/2099_01_01_000000_nowa_tabela.php'))->toBeTrue()
        ->and(file_exists($new.'/.env'))->toBeFalse()
        ->and(file_exists($new.'/storage'))->toBeFalse()
        ->and(file_exists($new.'/public/storage'))->toBeFalse();

    File::deleteDirectory($new);
    File::append(SiteUpdate::zipPath(), 'dopisane');
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(SiteUpdate::dir()), $changed, $changedCode);

    expect($changedCode)->toBe(1)
        ->and(implode("\n", $changed))->toContain('inna suma kontrolna')
        ->and(is_dir($new))->toBeFalse();
});

test('no new package is accepted while an install is waiting or running', function () {
    siteUpdateSend($this->actingAs($this->admin), siteUpdateZip($this->valid))->assertOk();
    Livewire::actingAs($this->admin)->test('pages::dashboard.site-update')->set('password', 'tajne-haslo')->call('install');

    siteUpdateSend($this->actingAs($this->admin), siteUpdateZip($this->valid))->assertStatus(422);

    File::makeDirectory(SiteUpdate::dir().'/blokada');
    Livewire::actingAs($this->admin)->test('pages::dashboard.site-update')->call('cancel');
    expect(SiteUpdate::flag())->not->toBeNull();
});
