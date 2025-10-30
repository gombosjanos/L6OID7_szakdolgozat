<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ajanlat;
use App\Models\AjanlatTetel;

class AjanlatController extends Controller
{
    public function showByWorkorder($munkalapId)
    {
        $ajanlat = Ajanlat::with('tetelek')->where('munkalap_id', $munkalapId)->first();
        if (!$ajanlat) {
            $ajanlat = Ajanlat::create([
                'munkalap_id' => $munkalapId,
                'statusz' => 'tervezet',
                'osszeg_brutto' => 0,
                'letrehozva' => now(),
            ]);
            $ajanlat->setRelation('tetelek', collect());
        }
        return response()->json($ajanlat);
    }

    public function upsert(Request $request, $munkalapId)
    {
        $data = $request->validate([
            'megjegyzes' => 'sometimes|nullable|string',
            'statusz' => 'sometimes|string|max:30',
            'tetelek' => 'array',
            'tetelek.*.id' => 'sometimes|integer',
            'tetelek.*.tipus' => 'sometimes|string|in:munkadij,alkatresz,egyedi',
            'tetelek.*.alkatresz_id' => 'sometimes|nullable|integer',
            'tetelek.*.megnevezes' => 'required|string',
            'tetelek.*.mennyiseg' => 'required|numeric',
            'tetelek.*.netto_egyseg_ar' => 'sometimes|numeric',
            'tetelek.*.brutto_egyseg_ar' => 'sometimes|numeric',
            'tetelek.*.afa_kulcs' => 'sometimes|numeric',
        ]);

        $ajanlat = Ajanlat::firstOrCreate(
            ['munkalap_id' => $munkalapId],
            ['statusz' => 'tervezet', 'osszeg_brutto' => 0, 'letrehozva' => now()]
        );

        if (isset($data['megjegyzes'])) $ajanlat->megjegyzes = $data['megjegyzes'];
        if (isset($data['statusz'])) $ajanlat->statusz = $data['statusz'];
        $ajanlat->save();

        if (isset($data['tetelek'])) {
            // simple replace strategy for MVP
            AjanlatTetel::where('ajanlat_id', $ajanlat->id)->delete();
            $sumBrutto = 0;
            foreach ($data['tetelek'] as $t) {
                $vat = isset($t['afa_kulcs']) ? (float)$t['afa_kulcs'] : 27.0;
                $netto = isset($t['netto_egyseg_ar']) ? (float)$t['netto_egyseg_ar'] : null;
                $brutto = isset($t['brutto_egyseg_ar']) ? (float)$t['brutto_egyseg_ar'] : null;
                if ($netto === null && $brutto !== null) {
                    $netto = $brutto / (1 + $vat/100.0);
                }
                if ($brutto === null && $netto !== null) {
                    $brutto = $netto * (1 + $vat/100.0);
                }
                $menny = (float)$t['mennyiseg'];
                $osszegBrutto = $menny * $brutto;
                $sumBrutto += $osszegBrutto;
                AjanlatTetel::create([
                    'ajanlat_id' => $ajanlat->id,
                    'tipus' => $t['tipus'] ?? 'egyedi',
                    'alkatresz_id' => $t['alkatresz_id'] ?? null,
                    'megnevezes' => $t['megnevezes'],
                    'mennyiseg' => $menny,
                    'egyseg_ar' => $netto ?? 0, // legacy compatibility
                    'osszeg' => $osszegBrutto,  // legacy: store brutto as osszeg
                    'netto_egyseg_ar' => $netto ?? 0,
                    'brutto_egyseg_ar' => $brutto ?? 0,
                    'afa_kulcs' => $vat,
                ]);
            }
            $ajanlat->osszeg_brutto = $sumBrutto;
            $ajanlat->save();
        }

        return response()->json(Ajanlat::with('tetelek')->find($ajanlat->id));
    }
}
