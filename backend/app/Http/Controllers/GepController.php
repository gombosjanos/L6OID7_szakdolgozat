<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gep;

class GepController extends Controller
{
    public function index(Request $request)
    {
        $query = Gep::query();
        if ($request->has('cikkszam')) {
            $needle = $request->query('cikkszam');
            $query->where('g_cikkszam', 'LIKE', "%{$needle}%");
        }
        if ($request->has('q')) {
            $q = $request->query('q');
            $query->where(function ($w) use ($q) {
                $w->where('g_cikkszam', 'LIKE', "%{$q}%")
                  ->orWhere('gyarto', 'LIKE', "%{$q}%")
                  ->orWhere('tipusnev', 'LIKE', "%{$q}%");
            });
        }
        $limit = (int)($request->query('limit', 20));
        $result = $query->limit(max(1, min($limit, 200)))->get();
        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'gyarto' => 'required|string|max:50',
            'tipusnev' => 'required|string|max:100',
            'g_cikkszam' => 'required|string|max:50',
            'gyartasiev' => 'required|digits:4',
        ]);
        $record = Gep::create($data);
        return response()->json($record, 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(string $id)
    {
        return response()->json(Gep::findOrFail($id), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, string $id)
    {
        $record = Gep::findOrFail($id);
        $data = $request->validate([
            'gyarto' => 'sometimes|string|max:50',
            'tipusnev' => 'sometimes|string|max:100',
            'g_cikkszam' => 'sometimes|string|max:50',
            'gyartasiev' => 'sometimes|digits:4',
        ]);
        $record->update($data);
        return response()->json($record, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(string $id)
    {
        Gep::findOrFail($id)->delete();
        return response()->json(['message' => 'Törölve'], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
