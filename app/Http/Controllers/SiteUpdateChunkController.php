<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Maintenance;
use App\Support\SiteUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Przyjmuje kolejne kawałki paczki typer-kod.zip z ekranu Panel → Aktualizacja strony.
 * Kawałki omijają limit wielkości jednego pliku na hostingu; błąd wraca jako JSON 422 z komunikatem dla admina.
 */
class SiteUpdateChunkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(Maintenance::canBypass($user), 403);

        $data = $request->validate([
            'upload' => ['required', 'string', 'max:40'],
            'index' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1', 'max:'.(int) ceil(SiteUpdate::MAX_BYTES / SiteUpdate::CHUNK_BYTES)],
            'chunk' => ['required', 'file', 'max:'.(intdiv(SiteUpdate::CHUNK_BYTES, 1024) + 64)],
        ]);

        $chunk = $request->file('chunk');
        abort_unless($chunk instanceof UploadedFile, 422);

        try {
            $package = SiteUpdate::receiveChunk((string) $data['upload'], (int) $data['index'], (int) $data['total'], $chunk, $user);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json(['done' => $package !== null, 'package' => $package]);
    }
}
