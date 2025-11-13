<?php

namespace App\Http\Controllers;

use App\Models\Felhasznalo;
use App\Traits\HandlesPhoneNumbers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FelhasznaloController extends Controller
{
    use HandlesPhoneNumbers;

    public function index(Request $request)
    {
        $q = $request->query('q');
        $limit = (int)($request->query('limit', 50));
        $query = Felhasznalo::query();
        // szerelő csak ügyfeleket láthat
        if ($request->user() && $request->user()->jogosultsag === 'szerelo') {
            $query->where('jogosultsag', 'Ugyfel');
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
        $messages = [
            'felhasznalonev.required' => 'A felhasználónév megadása kötelező.',
            'felhasznalonev.unique' => 'Ez a felhasználónév már foglalt.',
            'felhasznalonev.min' => 'A felhasználónév legalább 4 karakter legyen.',
            'felhasznalonev.regex' => 'A felhasználónév csak betűt, számot, pontot, aláhúzást vagy kötőjelet tartalmazhat.',
        ];

        $data = $r->validate([
            'nev' => 'required|string|min:2|max:50',
            'felhasznalonev' => 'required|string|min:4|max:50|regex:/^[A-Za-z0-9._-]+$/|unique:felhasznalok,felhasznalonev',
            'email' => 'required|email|max:100|unique:felhasznalok,email',
            'telefonszam' => ['required','string','max:32'],
            'jogosultsag' => 'required|string|in:admin,Ugyfel,szerelo',
            'password' => ['nullable','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/'],
        ], $messages);
        $data['telefonszam'] = $this->validateAndNormalizePhone($data['telefonszam']);
        $data['nev'] = trim($data['nev']);
        $data['email'] = trim($data['email']);
        $data['felhasznalonev'] = trim($data['felhasznalonev']);
        if (!empty($data['password'])) {
            $data['jelszo'] = bcrypt($data['password']);
        }
        unset($data['password']);
        $record = Felhasznalo::create($data);
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $r, $id)
    {
        $record = Felhasznalo::findOrFail($id);
        $pk = $record->getKeyName();
        $pkVal = $record->getKey();
        $messages = [
            'felhasznalonev.required' => 'A felhasználónév megadása kötelező.',
            'felhasznalonev.unique' => 'Ez a felhasználónév már foglalt.',
            'felhasznalonev.min' => 'A felhasználónév legalább 4 karakter legyen.',
            'felhasznalonev.regex' => 'A felhasználónév csak betűt, számot, pontot, aláhúzást vagy kötőjelet tartalmazhat.',
        ];

        $data = $r->validate([
            'nev' => 'sometimes|string|min:2|max:50',
            'felhasznalonev' => [
                'sometimes',
                'required',
                'string',
                'min:4',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('felhasznalok','felhasznalonev')->ignore($pkVal, $pk),
            ],
            'email' => [
                'sometimes',
                'email',
                'max:100',
                Rule::unique('felhasznalok','email')->ignore($pkVal, $pk),
            ],
            'telefonszam' => ['sometimes','required','string','max:32'],
            'jogosultsag' => 'sometimes|string|in:admin,Ugyfel,szerelo',
            'password' => ['sometimes','nullable','string','min:8','regex:/^(?=.*[A-Za-z])(?=.*\d).{8,}$/'],
        ], $messages);
        if (array_key_exists('telefonszam', $data)) {
            $data['telefonszam'] = $this->validateAndNormalizePhone($data['telefonszam'], $pkVal);
        }
        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $data['jelszo'] = bcrypt($data['password']);
            }
            unset($data['password']);
        }
        if (array_key_exists('felhasznalonev', $data)) {
            $data['felhasznalonev'] = trim($data['felhasznalonev']);
        }
        if (array_key_exists('nev', $data) && is_string($data['nev'])) {
            $data['nev'] = trim($data['nev']);
        }
        if (array_key_exists('email', $data) && is_string($data['email'])) {
            $data['email'] = trim($data['email']);
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
