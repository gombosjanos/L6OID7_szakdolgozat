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

    /**
     * List workorders (optional filters: q, limit, user_id).
     */
    public function index(Request $request)
    {
        $query = Munkalap::query()->with(['Ugyfel', 'gep']);
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
        // Backward-compat: also expose as 'azonosito'
        foreach ($items as $it) {
            $it->setAttribute('azonosito', $it->munkalapsorsz);
        }
        return response()->json($items, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Create a new workorder.
     */
    public function store(Request $request)
    {
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

        // Resolve customer: use user_id if given, else create/find by email
        if (empty($data['user_id'])) {
            if (!empty($data['Ugyfel_email'])) {
                $existing = Felhasznalo::where('email', $data['Ugyfel_email'])->first();
                if ($existing) {
                    $data['user_id'] = $existing->getKey();
                } else {
                    if (empty($data['Ugyfel_telefon'])) {
                        return response()->json([
                            'message' => 'Ä‚Ĺˇj Ä‚Ä˝gyfÄ‚Â©l rÄ‚Â¶gzÄ‚Â­tÄ‚Â©sÄ‚Â©hez telefonszÄ‚Ë‡m megadÄ‚Ë‡sa kÄ‚Â¶telezÄąâ€.'
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
                return response()->json(['message' => 'Ă„â€šÄąâ€şgyfÄ‚Â©l kivÄ‚Ë‡lasztÄ‚Ë‡sa vagy adatok megadÄ‚Ë‡sa kÄ‚Â¶telezÄąâ€'], 422, [], JSON_UNESCAPED_UNICODE);
            }
        }

        $payload = [
            'user_id' => $data['user_id'],
            'gep_id' => $data['gep_id'],
            'javitando_id' => $data['javitando_id'] ?? null,
            // Some schemas mark these NOT NULL Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ store empty string if not provided
            'hibaleiras' => array_key_exists('hibaleiras', $data) ? ($data['hibaleiras'] ?? '') : '',
            'megjegyzes' => array_key_exists('megjegyzes', $data) ? ($data['megjegyzes'] ?? '') : '',
            'statusz' => $data['statusz'] ?? 'uj',
            'letrehozva' => now(),
        ];

        $record = DB::transaction(function () use ($payload) {
            // Determine year from payload timestamp (or now)
            $ts = $payload['letrehozva'] ?? now();
            $yearInt = (int) date('Y', strtotime($ts));
            // Lock rows for this year and compute max suffix
            $maxSeq = DB::table('munkalapok')
                ->whereYear('letrehozva', $yearInt)
                ->whereNotNull('munkalapsorsz')
                ->lockForUpdate()
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(munkalapsorsz, '-', -1) AS UNSIGNED)) as m")
                ->value('m');
            $next = ((int)($maxSeq ?? 0)) + 1;
            $sorsz = $yearInt . '-' . $next;

            // Insert with the computed sequence to satisfy NOT NULL
            $rec = Munkalap::create(array_merge($payload, ['munkalapsorsz' => $sorsz]));
            return $rec;
        });
        // Backward-compat alias
        $record->setAttribute('azonosito', $record->munkalapsorsz);
        // Best-effort notification to customer
        try {
            $record->loadMissing('Ugyfel');
            if ($record->Ugyfel) {
                $record->Ugyfel->notify(new WorkorderCreated($record));
            }
        } catch (\Throwable $e) { /* ignore notify errors */ }
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Show a workorder with relations.
     */
    public function show(string $id)
    {
    $rec = Munkalap::with(['Ugyfel', 'gep', 'kepek'])->findOrFail($id);
        // Authorization for customers: can only view own workorders
        $auth = request()->user();
        if ($auth && $auth->jogosultsag === 'Ugyfel' && (int)$rec->user_id !== (int)$auth->getKey()) {
            abort(403, 'Nincs jogosultsÄ‚Ë‡g a munkalap megtekintÄ‚Â©sÄ‚Â©hez');
        }
        $rec->setAttribute('azonosito', $rec->munkalapsorsz);
        return response()->json($rec, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Update a workorder (admin/szerelo only).
     */
    public function update(Request $request, string $id)
    {
        $munkalap = Munkalap::findOrFail($id);
        $auth = $request->user();
        if (!$auth || !in_array($auth->jogosultsag, ['admin','szerelo'], true)) {
            abort(403, 'Nincs jogosultsÄ‚Ë‡g a mÄ‚Ĺ‚dosĂ„â€šĂ‚Â­tÄ‚Ë‡shoz');
        }
        $data = $request->validate([
            'statusz' => 'sometimes|string|max:50',
            'hibaleiras' => 'sometimes|string|nullable',
            'megjegyzes' => 'sometimes|string|nullable',
        ]);
        // Avoid writing NULL to NOT NULL columns
        if (array_key_exists('hibaleiras', $data) && $data['hibaleiras'] === null) { $data['hibaleiras'] = ''; }
        if (array_key_exists('megjegyzes', $data) && $data['megjegyzes'] === null) { $data['megjegyzes'] = ''; }
        $statusWas = $munkalap->statusz;
        $munkalap->update($data);
        if (isset($data['statusz']) && $data['statusz'] !== $statusWas) {
            MunkalapNaplo::create([
                'munkalap_id' => $munkalap->ID,
                'tipus' => 'statusz',
                'uzenet' => "Ä‚Âllapot vÄ‚Ë‡ltozott: {$statusWas} Ä‚ËĂ˘â‚¬Â Ă˘â‚¬â„˘ {$data['statusz']}",
                'letrehozva' => now(),
            ]);
        }
        // Notify customer on status change (best-effort)
        if (isset($data['statusz']) && $data['statusz'] !== $statusWas) {
            try {
                $munkalap->loadMissing('Ugyfel');
                if ($munkalap->Ugyfel) {
                    $munkalap->Ugyfel->notify(new WorkorderStatusChanged($munkalap, (string)$statusWas, (string)($data['statusz'] ?? '')));
                }
            } catch (\Throwable $e) { /* ignore notify errors */ }
        }
        return response()->json($munkalap->fresh(), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Delete a workorder (admin/szerelo only).
     */
    public function destroy(Request $request, string $id)
    {
        $auth = $request->user();
        if (!$auth || !in_array($auth->jogosultsag, ['admin','szerelo'], true)) {
            abort(403, 'Nincs jogosultsÄ‚Ë‡g a tÄ‚Â¶rlÄ‚Â©shez');
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
        try { if ($user) { $user->notify(new WorkorderDeleted($wid)); } } catch (\Throwable $e) { /* ignore */ }
        return response()->json(['message' => 'TÄ‚Â¶rÄ‚Â¶lve'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GenerÄ‚Ë‡l egy egyedi felhasznÄ‚Ë‡lÄ‚Ĺ‚nevet nÄ‚Â©v vagy e-mail alapjÄ‚Ë‡n.
     */
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


