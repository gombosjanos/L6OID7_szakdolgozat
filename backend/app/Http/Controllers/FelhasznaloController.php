<?php

namespace App\Http\Controllers;

use App\Models\Felhasznalo;
use Illuminate\Http\Request;

class FelhasznaloController extends Controller
{
    public function index()
    {
        // minden felhasználó JSON-ban
        return response()->json(Felhasznalo::all(), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        return response()->json(Felhasznalo::findOrFail($id), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'nev' => 'required|string|max:50',
            'felhasznalonev' => 'required|string|max:50|unique:felhasznalok',
            'email' => 'required|email|unique:felhasznalok',
            'telefonszam' => 'required|string|unique:felhasznalok',
            'jogosultsag' => 'required|string'
        ]);
        $record = Felhasznalo::create($data);
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $r, $id)
    {
        $record = Felhasznalo::findOrFail($id);
        $record->update($r->all());
        return response()->json($record, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy($id)
    {
        Felhasznalo::findOrFail($id)->delete();
        return response()->json(['message' => 'Törölve'], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
