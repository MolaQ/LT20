<?php

/**
 * Sprawdzanie i rozpakowanie paczki typer-kod.zip wgranej przez panel (Panel → Aktualizacja strony).
 * Ten sam plik czyta panel (sprawdzenie zaraz po wgraniu) i cron (narzedzia/instaluj-na-serwerze.sh):
 *   php narzedzia/rozpakuj-paczke.php FOLDER_AKTUALIZACJI
 * W trybie crona rozpakowuje paczkę do FOLDER/nowa tylko wtedy, gdy flaga.json z panelu ma sumę SHA-256
 * dokładnie tej paczki. Nie potrzebuje Laravela, więc działa też wtedy, gdy strona jest zepsuta.
 */

/** Pliki, które musi mieć każda paczka strony, od razu w głównym folderze zipa. */
const TYPER_PACZKA_WYMAGANE = ['artisan', 'public/index.php', 'vendor/autoload.php', 'bootstrap/wersja-paczki.txt'];

/**
 * Komunikat po polsku: w panelu przez tłumaczenia Laravela, w cronie wprost z lang/pl.json.
 *
 * @param  array<string, string|int>  $zamiany
 */
function typer_paczka_tekst(string $tekst, array $zamiany = []): string
{
    if (function_exists('__') && function_exists('app') && app()->bound('translator')) {
        return (string) __($tekst, $zamiany);
    }

    static $pl = null;
    $pl ??= json_decode((string) @file_get_contents(__DIR__.'/../lang/pl.json'), true) ?: [];
    $tekst = $pl[$tekst] ?? $tekst;

    foreach ($zamiany as $klucz => $wartosc) {
        $tekst = str_replace(':'.$klucz, (string) $wartosc, $tekst);
    }

    return $tekst;
}

/**
 * Nazwa wpisu bez „./” na początku (zip zrobiony przez tar z Windows) i z ukośnikami zamiast „\”.
 */
function typer_paczka_nazwa(string $nazwa): string
{
    $nazwa = str_replace('\\', '/', $nazwa);

    while (str_starts_with($nazwa, './')) {
        $nazwa = substr($nazwa, 2);
    }

    return $nazwa;
}

/**
 * Czy wpis z zipa wolno rozpakować: bez wychodzenia poza folder i bez nadpisywania ustawień, wgranych plików i logów.
 */
function typer_paczka_wpis_dozwolony(string $nazwa): bool
{
    $nazwa = typer_paczka_nazwa($nazwa);

    if ($nazwa === '' || $nazwa[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $nazwa) || preg_match('#^[A-Za-z]:#', $nazwa)) {
        return false;
    }

    foreach (['.env', 'storage/', 'public/storage', 'node_modules/', '.git/'] as $pomijane) {
        if ($nazwa === rtrim($pomijane, '/') || str_starts_with($nazwa, $pomijane)) {
            return false;
        }
    }

    return true;
}

/**
 * Sprawdza, czy zip jest paczką strony w poprawnym układzie.
 *
 * @return array{wersja: string, pliki: int, migracje: list<string>}
 *
 * @throws RuntimeException z komunikatem dla administratora
 */
function typer_sprawdz_paczke(string $zip): array
{
    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException(typer_paczka_tekst('The server is missing the PHP "zip" extension. Upload the package with the file manager.'));
    }

    $archiwum = new ZipArchive;

    if (! is_file($zip) || $archiwum->open($zip) !== true) {
        throw new RuntimeException(typer_paczka_tekst('The file is not a valid ZIP archive (it may have been uploaded only partly).'));
    }

    $nazwy = [];
    $wersja = '';

    for ($i = 0; $i < $archiwum->numFiles; $i++) {
        $surowa = (string) $archiwum->getNameIndex($i);
        $nazwa = typer_paczka_nazwa($surowa);
        $nazwy[$nazwa] = true;

        if ($nazwa === 'bootstrap/wersja-paczki.txt') {
            $wersja = trim((string) $archiwum->getFromIndex($i));
        }
    }

    foreach (TYPER_PACZKA_WYMAGANE as $wymagany) {
        if (! isset($nazwy[$wymagany])) {
            $zagniezdzony = array_values(array_filter(array_keys($nazwy), fn (string $nazwa) => str_ends_with($nazwa, '/'.$wymagany)));
            $archiwum->close();

            throw new RuntimeException($zagniezdzony
                ? typer_paczka_tekst('The package has an extra parent folder (:folder). Build it with paczka-na-serwer.bat.', ['folder' => dirname($zagniezdzony[0], substr_count($wymagany, '/') + 1)])
                : typer_paczka_tekst('This is not a site package: the file :file is missing. Build it with paczka-na-serwer.bat.', ['file' => $wymagany]));
        }
    }

    $migracje = [];

    foreach (array_keys($nazwy) as $nazwa) {
        if (preg_match('#^database/migrations/([^/]+\.php)$#', $nazwa, $trafienie)) {
            $migracje[] = $trafienie[1];
        }
    }

    sort($migracje);
    $pliki = count(array_filter(array_keys($nazwy), fn (string $nazwa) => ! str_ends_with($nazwa, '/')));
    $archiwum->close();

    if ($wersja === '') {
        throw new RuntimeException(typer_paczka_tekst('The package has no version number (empty file bootstrap/wersja-paczki.txt).'));
    }

    return ['wersja' => $wersja, 'pliki' => $pliki, 'migracje' => $migracje];
}

/**
 * Rozpakowuje paczkę do pustego folderu, pomijając .env, storage i public/storage.
 *
 * @return int liczba rozpakowanych plików
 */
function typer_rozpakuj_paczke(string $zip, string $cel): int
{
    $archiwum = new ZipArchive;

    if ($archiwum->open($zip) !== true) {
        throw new RuntimeException(typer_paczka_tekst('The package could not be opened.'));
    }

    if (! is_dir($cel) && ! mkdir($cel, 0755, true)) {
        throw new RuntimeException(typer_paczka_tekst('The folder :folder could not be created.', ['folder' => $cel]));
    }

    $wpisy = [];

    for ($i = 0; $i < $archiwum->numFiles; $i++) {
        $nazwa = (string) $archiwum->getNameIndex($i);

        if (typer_paczka_wpis_dozwolony($nazwa)) {
            $wpisy[] = $nazwa;
        }
    }

    if (! $archiwum->extractTo($cel, $wpisy)) {
        $archiwum->close();

        throw new RuntimeException(typer_paczka_tekst('Unpacking stopped (no disk space?).'));
    }

    $archiwum->close();

    return count(array_filter($wpisy, fn (string $nazwa) => ! str_ends_with($nazwa, '/')));
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $folder = rtrim((string) ($argv[1] ?? ''), '/');
    $zip = $folder.'/typer-kod.zip';
    $flaga = json_decode((string) @file_get_contents($folder.'/flaga.json'), true);

    try {
        if (! is_array($flaga) || ($flaga['typ'] ?? '') !== 'instaluj' || empty($flaga['sha256'])) {
            throw new RuntimeException(typer_paczka_tekst('No valid flag file: nobody approved the install in the panel.'));
        }

        if (! is_file($zip) || ! hash_equals((string) $flaga['sha256'], (string) hash_file('sha256', $zip))) {
            throw new RuntimeException(typer_paczka_tekst('The package on the server differs from the one approved in the panel (different checksum). Upload it again.'));
        }

        $paczka = typer_sprawdz_paczke($zip);
        $pliki = typer_rozpakuj_paczke($zip, $folder.'/nowa');

        echo typer_paczka_tekst('Package :version: :count files unpacked.', ['version' => $paczka['wersja'], 'count' => $pliki]).PHP_EOL;
        exit(0);
    } catch (Throwable $blad) {
        echo typer_paczka_tekst('ERROR in the package from the panel:').' '.$blad->getMessage().PHP_EOL;
        exit(1);
    }
}
