<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Aktualizacja strony z panelu: admin wgrywa paczkę typer-kod.zip w kawałkach, panel ją sprawdza,
 * a po zatwierdzeniu hasłem zostawia plik flagę z sumą SHA-256 paczki. Rozpakowuje i instaluje dopiero cron
 * (narzedzia/instaluj-na-serwerze.sh): bez flagi z sumą tej samej paczki nie rusza niczego.
 */
class SiteUpdate
{
    /** Największa paczka, jaką przyjmie panel. */
    public const MAX_BYTES = 400 * 1024 * 1024;

    /** Kawałek wysyłany jednym żądaniem: mniejszy niż typowe limity wysyłania plików na hostingu. */
    public const CHUNK_BYTES = 1024 * 1024;

    /** Inny folder roboczy (testy). */
    public static ?string $dirOverride = null;

    public static function dir(): string
    {
        return self::$dirOverride ?? storage_path('app/aktualizacja');
    }

    public static function zipPath(): string
    {
        return self::dir().'/typer-kod.zip';
    }

    /** Kopia poprzedniej wersji kodu robiona przez cron przed każdą aktualizacją z panelu (folder obok strony). */
    public static function rollbackPath(): string
    {
        return (self::$dirOverride ?? dirname(base_path()).'/kopie-typer').'/kod-poprzedni.tar.gz';
    }

    public static function installedVersion(): ?string
    {
        $file = base_path('bootstrap/wersja-paczki.txt');

        return is_file($file) ? (trim((string) file_get_contents($file)) ?: null) : null;
    }

    /** Cron właśnie rozpakowuje albo instaluje paczkę: w tym czasie panel niczego nie zmienia. */
    public static function locked(): bool
    {
        return is_dir(self::dir().'/blokada');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function json(string $name): ?array
    {
        $file = self::dir().'/'.$name;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Wgrana i sprawdzona paczka czekająca na decyzję.
     *
     * @return array<string, mixed>|null klucze: wersja, pliki, rozmiar, sha256, migracje, starsza, wgrano, kto
     */
    public static function package(): ?array
    {
        return is_file(self::zipPath()) ? self::json('paczka.json') : null;
    }

    /**
     * Zatwierdzona instalacja albo cofnięcie, które czeka na cron.
     *
     * @return array<string, mixed>|null
     */
    public static function flag(): ?array
    {
        return self::json('flaga.json');
    }

    /**
     * Ostatni wynik zapisany przez cron: stan (instalacja, gotowe, blad), opis i czas.
     *
     * @return array<string, mixed>|null
     */
    public static function status(): ?array
    {
        return self::json('stan.json');
    }

    /**
     * Przyjmuje kolejny kawałek paczki. Po ostatnim sprawdza całość i zwraca opis paczki.
     *
     * @return array<string, mixed>|null opis paczki po ostatnim kawałku
     *
     * @throws RuntimeException z komunikatem dla admina
     */
    public static function receiveChunk(string $uploadId, int $index, int $total, UploadedFile $chunk, User $user): ?array
    {
        if (! preg_match('/^[a-z0-9]{8,40}$/', $uploadId) || $total < 1 || $index < 0 || $index >= $total) {
            throw new RuntimeException(__('Invalid upload data. Refresh the page and try again.'));
        }

        if (self::locked() || self::flag()) {
            throw new RuntimeException(__('The previous package is being installed. Wait until it finishes.'));
        }

        File::ensureDirectoryExists(self::dir());
        $part = self::dir().'/wgrywanie-'.$uploadId.'.part';
        $counter = self::dir().'/wgrywanie-'.$uploadId.'.nr';

        if ($index === 0) {
            foreach (File::glob(self::dir().'/wgrywanie-*') as $stale) {
                File::delete($stale);
            }
            File::delete([self::zipPath(), self::dir().'/paczka.json']);
            File::put($part, '');
        }

        $expected = $index === 0 ? 0 : (int) (is_file($counter) ? file_get_contents($counter) : -1);

        if ($expected !== $index || ! is_file($part)) {
            throw new RuntimeException(__('The package parts arrived in the wrong order. Send the file again.'));
        }

        if ((int) filesize($part) + (int) $chunk->getSize() > self::MAX_BYTES) {
            File::delete([$part, $counter]);

            throw new RuntimeException(__('The package is too big (limit :mb MB).', ['mb' => intdiv(self::MAX_BYTES, 1024 * 1024)]));
        }

        $source = fopen((string) $chunk->getRealPath(), 'rb');
        $target = fopen($part, 'ab');

        if ($source === false || $target === false) {
            throw new RuntimeException(__('The package part could not be saved. Try again.'));
        }

        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);
        File::put($counter, (string) ($index + 1));

        if ($index + 1 < $total) {
            return null;
        }

        File::delete($counter);
        File::move($part, self::zipPath());

        return self::inspect($user);
    }

    /**
     * Sprawdza wgraną paczkę i zapisuje jej opis; złą paczkę od razu usuwa.
     *
     * @return array<string, mixed>
     */
    public static function inspect(User $user): array
    {
        require_once base_path('narzedzia/rozpakuj-paczke.php');

        try {
            $package = typer_sprawdz_paczke(self::zipPath());
        } catch (RuntimeException $error) {
            File::delete(self::zipPath());

            throw $error;
        }

        $installed = self::installedVersion();
        $known = collect(File::files(database_path('migrations')))->map(fn ($file) => $file->getFilename());
        $package = [
            'wersja' => $package['wersja'],
            'pliki' => $package['pliki'],
            'migracje' => collect($package['migracje'])->diff($known)->values()->all(),
            'rozmiar' => (int) filesize(self::zipPath()),
            'sha256' => (string) hash_file('sha256', self::zipPath()),
            'starsza' => $installed !== null && strcmp($package['wersja'], $installed) < 0,
            'wgrano' => now()->format('Y-m-d H:i:s'),
            'kto' => $user->name,
        ];

        File::put(self::dir().'/paczka.json', (string) json_encode($package, JSON_UNESCAPED_UNICODE));
        Audit::log('site_update.uploaded', null, [], ['version' => $package['wersja'], 'sha256' => $package['sha256'], 'size' => $package['rozmiar']]);

        return $package;
    }

    /** Zatwierdza instalację wgranej paczki: zostawia flagę dla crona. */
    public static function requestInstall(User $user): void
    {
        $package = self::package();

        if (! $package || self::flag() || self::locked()) {
            throw new RuntimeException(__('There is no package to install or an install is already waiting.'));
        }

        self::writeFlag(['typ' => 'instaluj', 'wersja' => (string) $package['wersja'], 'sha256' => (string) $package['sha256']], $user);
        Audit::log('site_update.approved', null, [], ['version' => $package['wersja'], 'sha256' => $package['sha256']]);
    }

    /** Zleca przywrócenie poprzedniej wersji kodu (kopia robiona przed każdą aktualizacją z panelu). */
    public static function requestRollback(User $user): void
    {
        if (! is_file(self::rollbackPath()) || self::flag() || self::locked()) {
            throw new RuntimeException(__('There is no copy of the previous version or another operation is already waiting.'));
        }

        self::writeFlag(['typ' => 'cofnij'], $user);
        Audit::log('site_update.rollback');
    }

    /**
     * @param  array<string, string>  $data
     */
    private static function writeFlag(array $data, User $user): void
    {
        File::ensureDirectoryExists(self::dir());
        File::delete(self::dir().'/stan.json');
        File::put(self::dir().'/flaga.json', (string) json_encode($data + ['kto' => $user->name, 'czas' => now()->format('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Wycofuje zatwierdzenie albo usuwa wgraną paczkę, dopóki cron nie zaczął pracy. */
    public static function cancel(): void
    {
        if (self::locked()) {
            throw new RuntimeException(__('The install is already running and cannot be stopped.'));
        }

        File::delete([self::dir().'/flaga.json', self::zipPath(), self::dir().'/paczka.json']);
        Audit::log('site_update.cancelled');
    }

    /** Ostatnie linie logu instalatora. */
    public static function logTail(int $lines = 30): string
    {
        $file = storage_path('logs/instalacja.log');
        $handle = is_file($file) ? fopen($file, 'rb') : false;

        if ($handle === false) {
            return '';
        }

        fseek($handle, max(0, (int) filesize($file) - 8192));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        return implode("\n", array_slice(explode("\n", trim($tail)), -$lines));
    }
}
