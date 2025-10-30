<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MunkalapController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = \App\Models\Munkalap::query();
        $auth = $request->user();
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }
        // If logged-in user is 'ugyfel', restrict to own records
        if ($auth && $auth->jogosultsag === 'ugyfel') {
            $query->where('user_id', $auth->getKey());
        }
        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
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
                $existing = \App\Models\Felhasznalo::where('email', $data['ugyfel_email'])->first();
                if ($existing) {
                    $data['user_id'] = $existing->getKey();
                } else {
                    $created = \App\Models\Felhasznalo::create([
                        'nev' => $data['ugyfel_nev'] ?? 'Ismeretlen',
                        'felhasznalonev' => $data['ugyfel_nev'] ?? null,
                        'email' => $data['ugyfel_email'],
                        'telefonszam' => $data['ugyfel_telefon'] ?? null,
                        'jogosultsag' => 'ugyfel',
                        // random jelszó (nem használ a felületen, csak megfelel a sémának)
                        'password' => bcrypt(str()->random(12)),
                    ]);
                    $data['user_id'] = $created->getKey();
                }
            } else {
                return response()->json(['message' => 'Ügyfél kiválasztása vagy adatok megadása kötelező'], 422);
            }
        }

        $payload = [
            'user_id' => $data['user_id'],
            'gep_id' => $data['gep_id'],
            'javitando_id' => $data['javitando_id'] ?? null,
            'hibaleiras' => $data['hibaleiras'] ?? null,
            'megjegyzes' => $data['megjegyzes'] ?? null,
            'statusz' => $data['statusz'] ?? 'uj',
            'letrehozva' => now(),
        ];

        $record = \App\Models\Munkalap::create($payload);
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $munkalap = \App\Models\Munkalap::findOrFail($id);
        $auth = $request->user();
        if (!$auth || !in_array($auth->jogosultsag, ['admin','szerelo'], true)) {
            abort(403, 'Nincs jogosultság a módosításhoz');
        }
        $data = $request->validate([
            'statusz' => 'sometimes|string|max:50',
            'hibaleiras' => 'sometimes|string|nullable',
            'megjegyzes' => 'sometimes|string|nullable',
        ]);
        $statusWas = $munkalap->statusz;
        $munkalap->update($data);
        // log status change
        if (isset($data['statusz']) && $data['statusz'] !== $statusWas) {
            \App\Models\MunkalapNaplo::create([
                'munkalap_id' => $munkalap->ID,
                'tipus' => 'statusz',
                'uzenet' => "Állapot változott: {$statusWas} → {$data['statusz']}",
                'letrehozva' => now(),
            ]);
        }
        return response()->json(['message' => 'Frissítve', 'munkalap' => $munkalap]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $auth = $request->user();
        if (!$auth || !in_array($auth->jogosultsag, ['admin','szerelo'], true)) {
            abort(403, 'Nincs jogosultság a törléshez');
        }
        $rec = \App\Models\Munkalap::findOrFail($id);
        $rec->delete();
        return response()->json(['message' => 'Törölve']);
    }
}
