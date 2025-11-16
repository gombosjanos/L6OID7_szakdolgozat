<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlkatreszController extends Controller
{
    public function show($id): JsonResponse
    {
        $table = 'alkatreszek';        
        $candidates = ['alkatresznev', 'alaktresznev', 'megnevezes'];
        $existing = array_values(array_intersect($candidates, Schema::getColumnListing($table)));
        $nameExpr = !empty($existing) ? ('COALESCE(' . implode(', ', $existing) . ')') : "''";

        $cols = Schema::getColumnListing($table);
        $hasNetto = in_array('nettoar', $cols, true);
        $hasBrutto = in_array('bruttoar', $cols, true);
        $hasVat = in_array('afa_kulcs', $cols, true);

        $select = "ID, a_cikkszam, {$nameExpr} as alaktresznev";
        if ($hasNetto) { $select .= ", nettoar"; }
        if ($hasBrutto) { $select .= ", bruttoar"; }
        if ($hasVat) { $select .= ", afa_kulcs"; }

        $row = DB::table($table)->selectRaw($select)->where('ID', $id)->first();
        if (!$row) { return response()->json(['message' => 'Nem található.'], 404); }
        return response()->json($row);
    }
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = (int) ($request->query('limit', 50));
        if ($limit <= 0 || $limit > 500) {
            $limit = 50;
        }

        $candidates = ['alkatresznev', 'alaktresznev', 'megnevezes'];
        $existing = array_values(array_intersect($candidates, Schema::getColumnListing('alkatreszek')));
        $nameExpr = "''";
        if (!empty($existing)) {
            $nameExpr = 'COALESCE(' . implode(', ', $existing) . ')';
        }

        $query = DB::table('alkatreszek')
            ->selectRaw("ID, a_cikkszam, {$nameExpr} as alaktresznev, nettoar, bruttoar, keszlet");

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
            'keszlet'        => 'sometimes|nullable|integer|min:0',
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
        if (array_key_exists('keszlet', $validated)) { $base['keszlet'] = (int)($validated['keszlet'] ?? 0); }

        $id = $this->insertWithNameFallback('alkatreszek', $base, $name);

        $candidates = ['alkatresznev', 'alaktresznev', 'megnevezes'];
        $existing = array_values(array_intersect($candidates, Schema::getColumnListing('alkatreszek')));
        $nameExpr = !empty($existing) ? ('COALESCE(' . implode(', ', $existing) . ')') : "''";
        $row = DB::table('alkatreszek')
            ->selectRaw("ID, a_cikkszam, {$nameExpr} as alaktresznev, nettoar, bruttoar, keszlet")
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
            'keszlet'        => 'sometimes|nullable|integer|min:0',
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
        if (array_key_exists('keszlet', $validated)) {
            $base['keszlet'] = (int)($validated['keszlet'] ?? 0);
        }

        $this->updateWithNameFallback('alkatreszek', (int)$id, $base, $name);

        $candidates = ['alkatresznev', 'alaktresznev', 'megnevezes'];
        $existing = array_values(array_intersect($candidates, Schema::getColumnListing('alkatreszek')));
        $nameExpr = !empty($existing) ? ('COALESCE(' . implode(', ', $existing) . ')') : "''";
        $row = DB::table('alkatreszek')
            ->selectRaw("ID, a_cikkszam, {$nameExpr} as alaktresznev, nettoar, bruttoar, keszlet")
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

    public function updateKeszlet(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'keszlet' => 'required|integer|min:0',
        ]);

        $alkatresz = DB::table('alkatreszek')->where('ID', $id)->first();

        if (!$alkatresz) {
            return response()->json(['message' => 'Az alkatrész nem található.'], 404);
        }

        DB::table('alkatreszek')->where('ID', $id)->update(['keszlet' => $validated['keszlet']]);

        $updatedAlkatresz = DB::table('alkatreszek')->where('ID', $id)->first();

        return response()->json($updatedAlkatresz);
    }

    private function insertWithNameFallback(string $table, array $base, ?string $name): int
    {
        $payload1 = $base;
        if ($name !== null) {
            $payload1['alkatresznev'] = $name;
        }
        try {
            return (int) DB::table($table)->insertGetId($payload1);
        } catch (QueryException $e) {
            $payload2 = $base;
            if ($name !== null) {
                $payload2['alaktresznev'] = $name;
            }
            return (int) DB::table($table)->insertGetId($payload2);
        }
    }

    private function updateWithNameFallback(string $table, int $id, array $base, ?string $name): void
    {
        $payload1 = $base;
        if ($name !== null) {
            $payload1['alkatresznev'] = $name;
        }
        try {
            DB::table($table)->where('ID', $id)->update($payload1);
        } catch (QueryException $e) {
            $payload2 = $base;
            if ($name !== null) {
                $payload2['alaktresznev'] = $name;
            }
            DB::table($table)->where('ID', $id)->update($payload2);
        }
    }
}
