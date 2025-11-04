<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Munkalap;
use App\Models\MunkalapNaplo;
use App\Models\Felhasznalo;
use Illuminate\Support\Facades\DB;

class MunkalapController extends Controller
{
    /**
     * List workorders (optional filters: q, limit, user_id).
     */
    public function index(Request $request)
    {
        $query = Munkalap::query()->with(['ugyfel', 'gep']);

        $auth = $request->user();
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }
        if ($auth && $auth->jogosultsag === 'ugyfel') {
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
            'ugyfel_nev' => 'sometimes|nullable|string|max:100',
            'ugyfel_email' => 'sometimes|nullable|email|max:100',
            'ugyfel_telefon' => 'sometimes|nullable|string|max:20',
            'gep_id' => 'required|integer',
            'javitando_id' => 'sometimes|nullable|integer',
            'hibaleiras' => 'sometimes|nullable|string',
            'megjegyzes' => 'sometimes|nullable|string',
            'statusz' => 'sometimes|string|max:50',
        ]);

        // Resolve customer: use user_id if given, else create/find by email
        if (empty($data['user_id'])) {
            if (!empty($data['ugyfel_email'])) {
                $existing = Felhasznalo::where('email', $data['ugyfel_email'])->first();
                if ($existing) {
                    $data['user_id'] = $existing->getKey();
                } else {
                    $created = Felhasznalo::create([
                        'nev' => $data['ugyfel_nev'] ?? 'Ismeretlen',
                        'felhasznalonev' => $data['ugyfel_nev'] ?? null,
                        'email' => $data['ugyfel_email'],
                        'telefonszam' => $data['ugyfel_telefon'] ?? null,
                        'jogosultsag' => 'ugyfel',
                        'password' => bcrypt(str()->random(12)),
                    ]);
                    $data['user_id'] = $created->getKey();
                }
            } else {
                return response()->json(['message' => 'ĂśgyfĂ©l kivĂˇlasztĂˇsa vagy adatok megadĂˇsa kĂ¶telezĹ‘'], 422, [], JSON_UNESCAPED_UNICODE);
            }
        }

        $payload = [
            'user_id' => $data['user_id'],
            'gep_id' => $data['gep_id'],
            'javitando_id' => $data['javitando_id'] ?? null,
            // Some schemas mark these NOT NULL â†’ store empty string if not provided
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
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Show a workorder with relations.
     */
    public function show(string $id)
    {
        $rec = Munkalap::with(['ugyfel', 'gep'])->findOrFail($id);
        // Authorization for customers: can only view own workorders
        $auth = request()->user();
        if ($auth && $auth->jogosultsag === 'ugyfel' && (int)$rec->user_id !== (int)$auth->getKey()) {
            abort(403, 'Nincs jogosultsĂˇg a munkalap megtekintĂ©sĂ©hez');
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
            abort(403, 'Nincs jogosultsĂˇg a mĂłdosĂ­tĂˇshoz');
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
                'uzenet' => "Ăllapot vĂˇltozott: {$statusWas} â†’ {$data['statusz']}",
                'letrehozva' => now(),
            ]);
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
            abort(403, 'Nincs jogosultsĂˇg a tĂ¶rlĂ©shez');
        }
        $rec = Munkalap::findOrFail($id);
        $rec->delete();
        return response()->json(['message' => 'TĂ¶rĂ¶lve'], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
