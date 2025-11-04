<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MunkalapNaplo;

class MunkalapNaploController extends Controller
{
    public function index($munkalapId)
    {
        $items = MunkalapNaplo::where('munkalap_id', $munkalapId)
            ->orderBy('letrehozva', 'asc')
            ->get();
        return response()->json($items, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function store(Request $request, $munkalapId)
    {
        // Backward-compat: accept {szoveg} and default tipus to 'jegyzet'
        $merge = [];
        if (!$request->filled('tipus')) { $merge['tipus'] = 'jegyzet'; }
        if (!$request->filled('uzenet') && $request->filled('szoveg')) { $merge['uzenet'] = $request->input('szoveg'); }
        if (!empty($merge)) { $request->merge($merge); }

        $data = $request->validate([
            'tipus' => 'required|string|max:30', // pl. statusz|jegyzet
            'uzenet' => 'required|string',
        ]);
        $rec = MunkalapNaplo::create([
            'munkalap_id' => $munkalapId,
            'tipus' => $data['tipus'],
            'uzenet' => $data['uzenet'],
            'letrehozva' => now(),
        ]);
        return response()->json($rec, 201, [], JSON_UNESCAPED_UNICODE);
    }
}
