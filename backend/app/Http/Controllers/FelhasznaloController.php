<?php

namespace App\Http\Controllers;

use App\Models\Felhasznalo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FelhasznaloController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $limit = (int)($request->query('limit', 50));
        $query = Felhasznalo::query();
        // szerelĹ‘ csak ĂĽgyfeleket lĂˇthat
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
            'nev' => 'required|string|min:2|max:50',
            'felhasznalonev' => 'nullable|string|max:50|unique:felhasznalok,felhasznalonev',
            'email' => 'required|email|max:100|unique:felhasznalok,email',
            'telefonszam' => ['nullable','string','regex:/^\+?[0-9\s-]{6,20}$/'],
            'jogosultsag' => 'required|string|in:admin,ugyfel,szerelo',
            'password' => ['nullable','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/'],
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
            'nev' => 'sometimes|string|min:2|max:50',
            'felhasznalonev' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('felhasznalok','felhasznalonev')->ignore($pkVal, $pk),
            ],
            'email' => [
                'sometimes',
                'email',
                'max:100',
                Rule::unique('felhasznalok','email')->ignore($pkVal, $pk),
            ],
            'telefonszam' => ['sometimes','nullable','string','regex:/^\+?[0-9\s-]{6,20}$/'],
            'jogosultsag' => 'sometimes|string|in:admin,ugyfel,szerelo',
            'password' => ['sometimes','nullable','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/'],
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
        return response()->json(['message' => 'TĂ¶rĂ¶lve'], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
