<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Munkalap;
use App\Models\MunkalapNaplo;
use App\Models\Felhasznalo;
use App\Traits\HandlesPhoneNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Notifications\WorkorderCreated;
use App\Notifications\WorkorderStatusChanged;
use App\Notifications\WorkorderDeleted;
class MunkalapController extends Controller
{
    use HandlesPhoneNumbers;

    public function index(Request $request)
    {
        $query = Munkalap::query()->with(['Ugyfel', 'gep', 'letrehozo']);
        $auth = $request->user();
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }
        if ($auth && $auth->jogosultsag === 'Ugyfel') {
            $query->where('user_id', $auth->getKey());
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($w) use ($q) {
                $w->where('ID', 'LIKE', "%{$q}%")
                  ->orWhere('hibaleiras', 'LIKE', "%{$q}%")
                  ->orWhere('megjegyzes', 'LIKE', "%{$q}%");
            });
        }

        if ($request->filled('limit')) {
            $limit = (int) $request->query('limit', 50);
            $query->limit(max(1, min($limit, 200)));
        }

        $items = $query->orderByDesc('ID')->get();
        foreach ($items as $it) {
            $it->setAttribute('azonosito', $it->munkalapsorsz);
        }
        return response()->json($items, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request)
    {
        $auth = $request->user();

        $data = $request->validate([
            'regisztralt' => 'sometimes|boolean',
            'user_id' => 'sometimes|nullable|integer',
            'Ugyfel_nev' => 'sometimes|nullable|string|max:100',
            'Ugyfel_email' => 'sometimes|nullable|email|max:100',
            'Ugyfel_telefon' => 'sometimes|nullable|string|max:32',
            'gep_id' => 'required|integer',
            'javitando_id' => 'sometimes|nullable|integer',
            'hibaleiras' => 'sometimes|nullable|string',
            'megjegyzes' => 'sometimes|nullable|string',
            'statusz' => 'sometimes|string|max:50',
        ]);

        if (empty($data['user_id'])) {
            if (!empty($data['Ugyfel_email'])) {
                $existing = Felhasznalo::where('email', $data['Ugyfel_email'])->first();
                if ($existing) {
                    $data['user_id'] = $existing->getKey();
                } else {
                    if (empty($data['Ugyfel_telefon'])) {
                        return response()->json([
                            'message' => 'Új ügyfél felvételéhez a telefonszám megadása kötelező.'
                        ], 422, [], JSON_UNESCAPED_UNICODE);
                    }

                    $normalizedPhone = $this->validateAndNormalizePhone($data['Ugyfel_telefon']);
                    $generatedUsername = $this->generateUniqueUsername($data['Ugyfel_nev'] ?? null, $data['Ugyfel_email']);

                    $created = Felhasznalo::create([
                        'nev' => $data['Ugyfel_nev'] ?? 'Ismeretlen',
                        'felhasznalonev' => $generatedUsername,
                        'email' => $data['Ugyfel_email'],
                        'telefonszam' => $normalizedPhone,
                        'jogosultsag' => 'Ugyfel',
                        'jelszo' => bcrypt(Str::random(12)),
                    ]);
                    $data['user_id'] = $created->getKey();
                }
            } else {
                return response()->json(['message' => 'Ügyfél kiválasztása vagy adatok megadása kötelező'], 422, [], JSON_UNESCAPED_UNICODE);
            }
        }

        $payload = [
            'user_id' => $data['user_id'],
            'letrehozta' => $auth ? $auth->getKey() : ($data['user_id'] ?? null),
            'gep_id' => $data['gep_id'],
            'javitando_id' => $data['javitando_id'] ?? null,
            'hibaleiras' => array_key_exists('hibaleiras', $data) ? ($data['hibaleiras'] ?? '') : '',
            'megjegyzes' => array_key_exists('megjegyzes', $data) ? ($data['megjegyzes'] ?? '') : '',
            'statusz' => $data['statusz'] ?? 'uj',
            'letrehozva' => now(),
        ];

        $record = DB::transaction(function () use ($payload) {
            $ts = $payload['letrehozva'] ?? now();
            $yearInt = (int) date('Y', strtotime($ts));
            $maxSeq = DB::table('munkalapok')
                ->whereYear('letrehozva', $yearInt)
                ->whereNotNull('munkalapsorsz')
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(munkalapsorsz, '-', -1) AS UNSIGNED)) as m")
                ->value('m');
            $next = ((int)($maxSeq ?? 0)) + 1;
            $sorsz = $yearInt . '-' . $next;

            $rec = Munkalap::create(array_merge($payload, ['munkalapsorsz' => $sorsz]));
            return $rec;
        });
        $record->setAttribute('azonosito', $record->munkalapsorsz);
        try {
            $record->loadMissing('Ugyfel');
            if ($record->Ugyfel) {
                $record->Ugyfel->notify(new WorkorderCreated($record));
            }
        } catch (\Throwable $e) { }
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(string $id)
    {
        $rec = Munkalap::with(['Ugyfel', 'gep', 'kepek', 'letrehozo'])->findOrFail($id);
        $auth = request()->user();
        if ($auth && $auth->jogosultsag === 'Ugyfel' && (int)$rec->user_id !== (int)$auth->getKey()) {
            abort(403, 'Nincs jogosultság a munkalap megtekintéséhez');
        }
        $rec->setAttribute('azonosito', $rec->munkalapsorsz);
        return response()->json($rec, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, string $id)
    {
        $munkalap = Munkalap::findOrFail($id);
        $auth = $request->user();
        if (!$auth || !in_array($auth->jogosultsag, ['admin','szerelo'], true)) {
            abort(403, 'Nincs jogosultság a módosí­táshoz');
        }
        $data = $request->validate([
            'user_id' => 'sometimes|integer|exists:felhasznalok,id',
            'gep_id' => 'sometimes|integer|exists:gepek,ID',
            'javitando_id' => 'sometimes|nullable|integer',
            'statusz' => 'sometimes|string|max:50',
            'hibaleiras' => 'sometimes|string|nullable',
            'megjegyzes' => 'sometimes|string|nullable',
        ]);
        if (array_key_exists('hibaleiras', $data) && $data['hibaleiras'] === null) { $data['hibaleiras'] = ''; }
        if (array_key_exists('megjegyzes', $data) && $data['megjegyzes'] === null) { $data['megjegyzes'] = ''; }
        $statusWas = $munkalap->statusz;
        $munkalap->update($data);
        if (isset($data['statusz']) && $data['statusz'] !== $statusWas) {
            MunkalapNaplo::create([
                'munkalap_id' => $munkalap->ID,
                'tipus' => 'statusz',
                'uzenet' => "Állapot változott: {$statusWas} -> {$data['statusz']}",
                'letrehozva' => now(),
            ]);
        }
        if (isset($data['statusz']) && $data['statusz'] !== $statusWas) {
            try {
                $munkalap->loadMissing('Ugyfel');
                if ($munkalap->Ugyfel) {
                    $munkalap->Ugyfel->notify(new WorkorderStatusChanged($munkalap, (string)$statusWas, (string)($data['statusz'] ?? '')));
                }
            } catch (\Throwable $e) { }
        }
        return response()->json($munkalap->fresh(), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, string $id)
    {
        $auth = $request->user();
        if (!$auth || !in_array($auth->jogosultsag, ['admin','szerelo'], true)) {
            abort(403, 'Nincs jogosultság a törléshez');
        }
        $rec = Munkalap::with(['kepek','Ugyfel'])->findOrFail($id);
        $user = $rec->Ugyfel;
        $wid = (int) $rec->ID;
        foreach ($rec->kepek as $kep) {
            if ($kep->fajlnev) {
                Storage::disk('public')->delete($kep->fajlnev);
            }
        }
        $rec->delete();
        try { if ($user) { $user->notify(new WorkorderDeleted($wid)); } } catch (\Throwable $e) {  }
        return response()->json(['message' => 'Törölve'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    protected function generateUniqueUsername(?string $name, ?string $email): string
    {
        $candidates = [];
        if ($email && str_contains($email, '@')) {
            $candidates[] = strstr($email, '@', true);
        }
        if ($name) {
            $candidates[] = $name;
        }
        $candidates[] = 'Ugyfel';

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $lower = mb_strtolower($candidate, 'UTF-8');
            $base = preg_replace('/[^a-z0-9._-]/', '', $lower ?? '');
            $base = trim((string) $base, '._-');

            if ($base === '') {
                continue;
            }

            if (strlen($base) < 4) {
                $base = str_pad($base, 4, '0');
            }

            $base = substr($base, 0, 40);
            $username = $base;
            $suffix = 1;

            while (Felhasznalo::where('felhasznalonev', $username)->exists()) {
                $suffix++;
                $username = substr($base, 0, max(1, 50 - strlen((string) $suffix))) . $suffix;
            }

            return $username;
        }

        do {
            $username = 'Ugyfel' . random_int(1000, 9999);
        } while (Felhasznalo::where('felhasznalonev', $username)->exists());

        return $username;
    }
}
