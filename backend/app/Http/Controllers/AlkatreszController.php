<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlkatreszController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = (int) ($request->query('limit', 50));
        if ($limit <= 0 || $limit > 500) {
            $limit = 50;
        }

        // Build a safe COALESCE only over existing columns
        $candidates = ['alkatresznev', 'alaktresznev', 'megnevezes'];
        $existing = array_values(array_intersect($candidates, Schema::getColumnListing('alkatreszek')));
        $nameExpr = "''"; // default empty string if none exists
        if (!empty($existing)) {
            $nameExpr = 'COALESCE(' . implode(', ', $existing) . ')';
        }

        $query = DB::table('alkatreszek')
            ->selectRaw("ID, a_cikkszam, {$nameExpr} as alaktresznev, nettoar, bruttoar");

        if ($q !== '') {
            $like = "%" . str_replace(['%', '_'], ['\\%', '\\_'], $q) . "%";
            $query->where(function ($w) use ($like, $existing) {
                $w->where('a_cikkszam', 'like', $like);
                foreach ($existing as $col) {
                    $w->orWhere($col, 'like', $like);
                }
            });
        }

        $rows = $query->limit($limit)->get();

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'a_cikkszam'     => 'required|string|max:255',
            'alkatresznev'   => 'sometimes|nullable|string|max:1000',
            'alaktresznev'   => 'sometimes|nullable|string|max:1000',
            'megnevezes'     => 'sometimes|nullable|string|max:1000',
            'nettoar'        => 'sometimes|nullable|numeric|min:0',
            'bruttoar'       => 'sometimes|nullable|numeric|min:0',
            'afa_kulcs'      => 'sometimes|numeric|min:0|max:100',
        ]);

        $name = $validated['alaktresznev']
            ?? $validated['alkatresznev']
            ?? $validated['megnevezes']
            ?? null;

        $vat = isset($validated['afa_kulcs']) ? (float)$validated['afa_kulcs'] : 27.0;
        $netto = isset($validated['nettoar']) ? (float)$validated['nettoar'] : null;
        $brutto = isset($validated['bruttoar']) ? (float)$validated['bruttoar'] : null;
        if ($netto === null && $brutto === null) {
            $netto = 0.0;
            $brutto = 0.0;
        } elseif ($netto === null) {
            $netto = round($brutto / (1 + $vat / 100), 2);
        } elseif ($brutto === null) {
            $brutto = round($netto * (1 + $vat / 100), 2);
        }

        $base = [
            'a_cikkszam' => $validated['a_cikkszam'],
            'nettoar'    => $netto,
            'bruttoar'   => $brutto,
        ];

        $id = $this->insertWithNameFallback('alkatreszek', $base, $name);

        $row = DB::table('alkatreszek')
            ->selectRaw('ID, a_cikkszam, COALESCE(alkatresznev, alaktresznev) as alaktresznev, nettoar, bruttoar')
            ->where('ID', $id)
            ->first();

        return response()->json($row, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'a_cikkszam'     => 'sometimes|string|max:255',
            'alkatresznev'   => 'sometimes|nullable|string|max:1000',
            'alaktresznev'   => 'sometimes|nullable|string|max:1000',
            'megnevezes'     => 'sometimes|nullable|string|max:1000',
            'nettoar'        => 'sometimes|nullable|numeric|min:0',
            'bruttoar'       => 'sometimes|nullable|numeric|min:0',
            'afa_kulcs'      => 'sometimes|numeric|min:0|max:100',
        ]);

        $row = DB::table('alkatreszek')->where('ID', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Nem található.'], 404);
        }

        $name = $validated['alaktresznev']
            ?? $validated['alkatresznev']
            ?? $validated['megnevezes']
            ?? null;

        $vat = isset($validated['afa_kulcs']) ? (float)$validated['afa_kulcs'] : 27.0;
        $netto = array_key_exists('nettoar', $validated) ? (float)$validated['nettoar'] : null;
        $brutto = array_key_exists('bruttoar', $validated) ? (float)$validated['bruttoar'] : null;

        // Derive missing value only if one provided
        if ($netto !== null && $brutto === null) {
            $brutto = round($netto * (1 + $vat / 100), 2);
        } elseif ($brutto !== null && $netto === null) {
            $netto = round($brutto / (1 + $vat / 100), 2);
        }

        $base = [];
        if (array_key_exists('a_cikkszam', $validated)) {
            $base['a_cikkszam'] = $validated['a_cikkszam'];
        }
        if ($netto !== null) {
            $base['nettoar'] = $netto;
        }
        if ($brutto !== null) {
            $base['bruttoar'] = $brutto;
        }

        $this->updateWithNameFallback('alkatreszek', (int)$id, $base, $name);

        $row = DB::table('alkatreszek')
            ->selectRaw('ID, a_cikkszam, COALESCE(alkatresznev, alaktresznev) as alaktresznev, nettoar, bruttoar')
            ->where('ID', $id)
            ->first();

        return response()->json($row);
    }

    public function destroy($id): JsonResponse
    {
        $deleted = DB::table('alkatreszek')->where('ID', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Nem található.'], 404);
        }
        return response()->json(['message' => 'Sikeresen törölve.']);
    }

    private function insertWithNameFallback(string $table, array $base, ?string $name): int
    {
        $payload1 = $base;
        if ($name !== null) {
            $payload1['alkatresznev'] = $name; // elsődlegesen ezt próbáljuk
        }
        try {
            return (int) DB::table($table)->insertGetId($payload1);
        } catch (QueryException $e) {
            // Ha oszlopnév gond, próbáljuk a másik változatot
            $payload2 = $base;
            if ($name !== null) {
                $payload2['alaktresznev'] = $name;
            }
            return (int) DB::table($table)->insertGetId($payload2);
        }
    }

    private function updateWithNameFallback(string $table, int $id, array $base, ?string $name): void
    {
        // Próbáljuk először az "alkatresznev" mezőt
        $payload1 = $base;
        if ($name !== null) {
            $payload1['alkatresznev'] = $name;
        }
        try {
            DB::table($table)->where('ID', $id)->update($payload1);
        } catch (QueryException $e) {
            // Ha oszlopnév gond, próbáljuk az "alaktresznev" mezőt
            $payload2 = $base;
            if ($name !== null) {
                $payload2['alaktresznev'] = $name;
            }
            DB::table($table)->where('ID', $id)->update($payload2);
        }
    }
}
