<?php

namespace App\Http\Controllers;

use App\Models\Felhasznalo;
use Illuminate\Http\Request;

class FelhasznaloController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $limit = (int)($request->query('limit', 50));
        $query = Felhasznalo::query();
        // szerelő csak ügyfeleket láthat
        if ($request->user() && $request->user()->jogosultsag === 'szerelo') {
            $query->where('jogosultsag', 'ugyfel');
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('nev', 'LIKE', "%{$q}%")
                  ->orWhere('email', 'LIKE', "%{$q}%")
                  ->orWhere('felhasznalonev', 'LIKE', "%{$q}%");
            });
        }
        $items = $query->limit(max(1, min($limit, 200)))->get();
        return response()->json($items, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        return response()->json(Felhasznalo::findOrFail($id), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'nev' => 'required|string|max:50',
            'felhasznalonev' => 'nullable|string|max:50|unique:felhasznalok,felhasznalonev',
            'email' => 'required|email|unique:felhasznalok,email',
            'telefonszam' => 'nullable|string|max:20',
            'jogosultsag' => 'required|string|in:admin,ugyfel,szerelo',
            'password' => 'nullable|string|min:6',
        ]);
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }
        $record = Felhasznalo::create($data);
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $r, $id)
    {
        $record = Felhasznalo::findOrFail($id);
        $pk = $record->getKeyName();
        $pkVal = $record->getKey();
        $data = $r->validate([
            'nev' => 'sometimes|string|max:50',
            'felhasznalonev' => 'sometimes|nullable|string|max:50|unique:felhasznalok,felhasznalonev,'.$pkVal.','.$pk,
            'email' => 'sometimes|email|unique:felhasznalok,email,'.$pkVal.','.$pk,
            'telefonszam' => 'sometimes|nullable|string|max:20',
            'jogosultsag' => 'sometimes|string|in:admin,ugyfel,szerelo',
            'password' => 'sometimes|nullable|string|min:6',
        ]);
        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }
        }
        $record->update($data);
        return response()->json($record, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy($id)
    {
        Felhasznalo::findOrFail($id)->delete();
        return response()->json(['message' => 'Törölve'], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
