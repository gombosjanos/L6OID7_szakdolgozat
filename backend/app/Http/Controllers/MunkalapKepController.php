<?php

namespace App\Http\Controllers;

use App\Models\Munkalap;
use App\Models\MunkalapKep;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MunkalapKepController extends Controller
{
    public function index(Request $request, string $id)
    {
        $munkalap = Munkalap::with('kepek')->findOrFail($id);
        $auth = $request->user();
        if ($auth && $auth->jogosultsag === 'Ugyfel' && (int) $munkalap->user_id !== (int) $auth->getKey()) {
            abort(403, 'Nincs jogosultság a képek megtekintéséhez.');
        }

        return response()->json($munkalap->kepek, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Request $request, string $id, string $kepId)
    {
        $munkalap = Munkalap::findOrFail($id);
        $auth = $request->user();
        if ($auth && $auth->jogosultsag === 'Ugyfel' && (int) $munkalap->user_id !== (int) $auth->getKey()) {
            abort(403, 'Nincs jogosultság a képek megtekintéséhez.');
        }

        $kep = MunkalapKep::where('munkalap_id', $munkalap->ID)
            ->where('id', $kepId)
            ->firstOrFail();

        $disk = Storage::disk('public');
        $path = $kep->fajlnev;
        if (!$path || !$disk->exists($path)) {
            abort(404, 'Kép nem található.');
        }

        $mime = $kep->mime ?: ($disk->mimeType($path) ?: 'application/octet-stream');
        $filename = $kep->eredeti_nev ?: basename($path);

        $stream = $disk->readStream($path);
        if ($stream === false) {
            abort(404, 'A képfájl nem olvasható.');
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'private, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, string $id)
    {
        $munkalap = Munkalap::findOrFail($id);

        $files = $request->file('kepek', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if ($request->hasFile('kep')) {
            $files[] = $request->file('kep');
        }

        $files = array_filter($files, fn ($file) => $file instanceof UploadedFile);

        if (empty($files)) {
            return response()->json([
                'message' => 'Legalább egy képet fel kell tölteni.',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $messages = [
            'kepek.*.image' => 'Csak képfájl tölthető fel (jpg, png, webp, heic, avif).',
            'kepek.*.mimes' => 'Csak képfájl tölthető fel (jpg, png, webp, heic, avif).',
            'kepek.*.max' => 'A képfájl mérete legfeljebb 10 MB lehet.',
        ];

        $request->validate([
            'kepek.*' => 'image|mimes:jpg,jpeg,png,webp,avif,gif,heic|max:10240',
            'kep' => 'sometimes|image|mimes:jpg,jpeg,png,webp,avif,gif,heic|max:10240',
        ], $messages);

        $stored = [];
        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }

            $path = $file->store('munkalapok/' . $munkalap->ID, 'public');
            $record = MunkalapKep::create([
                'munkalap_id' => $munkalap->ID,
                'fajlnev' => $path,
                'eredeti_nev' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'meret' => $file->getSize(),
                'feltolto_id' => optional($request->user())->getKey(),
                'letrehozva' => now(),
            ]);

            $stored[] = $record->fresh();
        }

        if (empty($stored)) {
            return response()->json([
                'message' => 'Nem sikerült egyetlen képet sem feldolgozni.',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'kepek' => $stored,
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, string $id, string $kepId)
    {
        $munkalap = Munkalap::findOrFail($id);
        $kep = MunkalapKep::where('munkalap_id', $munkalap->ID)->where('id', $kepId)->firstOrFail();

        if ($kep->fajlnev) {
            Storage::disk('public')->delete($kep->fajlnev);
        }

        $kep->delete();

        return response()->json([
            'message' => 'Kép törölve.',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
