<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ajanlat;
use App\Models\AjanlatTetel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AjanlatController extends Controller
{
    public function showByWorkorder($munkalapId)
    {
        // Van-e ügyfélnek jogosultsága?
        $auth = request()->user();
        if ($auth && $auth->jogosultsag === 'Ugyfel') {
            $ml = \App\Models\Munkalap::findOrFail($munkalapId);
            if ((int)$ml->user_id !== (int)$auth->getKey()) {
                abort(403, 'Nincs jogosultság');
            }
        }

        $ajanlat = Ajanlat::where('munkalap_id', $munkalapId)->first();
        if (!$ajanlat) {
            $hdrTable = 'munkalap_ajanlat';
            $header = null;
            if (Schema::hasColumn($hdrTable, 'MunkalapID')) {
                $header = DB::table($hdrTable)->where('MunkalapID', $munkalapId)->first();
            } elseif (Schema::hasColumn($hdrTable, 'munkalapId')) {
                $header = DB::table($hdrTable)->where('munkalapId', $munkalapId)->first();
            }
            if ($header) {
                $aid = $header->ID ?? $header->id ?? null;
                $fk = Schema::hasColumn('munkalap_ajanlat_tetelek','ajanlat_id') ? 'ajanlat_id' : (Schema::hasColumn('munkalap_ajanlat_tetelek','AjanlatID') ? 'AjanlatID' : (Schema::hasColumn('munkalap_ajanlat_tetelek','ajanlatId') ? 'ajanlatId' : 'ajanlat_id'));
                $rows = $aid ? DB::table('munkalap_ajanlat_tetelek')->where($fk, $aid)->get() : collect();
                if ($rows instanceof \Illuminate\Support\Collection) {
                    $rows = $rows->map(function ($r) {
                        if (empty($r->tipus) && (!empty($r->alkatresz_id) || !empty($r->AlkatreszID) || !empty($r->alkatreszId))) {
                            $r->tipus = 'alkatresz';
                        }
                        return $r;
                    });
                }
                return response()->json((object)[
                    'ID' => $aid,
                    'statusz' => $header->statusz ?? null,
                    'megjegyzes' => $header->megjegyzes ?? null,
                    'tetelek' => $rows,
                ]);
            }    
            return response()->json((object)['statusz'=>null,'megjegyzes'=>null,'tetelek'=>[]]);
        }
        // Tételek betöltése
        $aid = $ajanlat->ID ?? $ajanlat->id ?? $ajanlat->getKey();
        $fk = Schema::hasColumn('munkalap_ajanlat_tetelek','ajanlat_id') ? 'ajanlat_id' : (Schema::hasColumn('munkalap_ajanlat_tetelek','AjanlatID') ? 'AjanlatID' : (Schema::hasColumn('munkalap_ajanlat_tetelek','ajanlatId') ? 'ajanlatId' : 'ajanlat_id'));
        $rows = DB::table('munkalap_ajanlat_tetelek')->where($fk, $aid)->get();
        $isEmpty = ($rows instanceof \Illuminate\Support\Collection) ? $rows->isEmpty() : (empty($rows));
        if ($isEmpty) {
            $altFk = Schema::hasColumn('munkalap_ajanlat_tetelek','munkalap_id') ? 'munkalap_id' : (Schema::hasColumn('munkalap_ajanlat_tetelek','MunkalapID') ? 'MunkalapID' : (Schema::hasColumn('munkalap_ajanlat_tetelek','munkalapId') ? 'munkalapId' : null));
            if ($altFk) { $rows = DB::table('munkalap_ajanlat_tetelek')->where($altFk, $munkalapId)->get(); }
        }
        if ($rows instanceof \Illuminate\Support\Collection) {
            $rows = $rows->map(function ($r) {
                if (empty($r->tipus) && (!empty($r->alkatresz_id) || !empty($r->AlkatreszID) || !empty($r->alkatreszId))) {
                    $r->tipus = 'alkatresz';
                }
                return $r;
            });
        }
        $ajanlat->setRelation('tetelek', $rows);
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

        $result = DB::transaction(function () use ($data, $munkalapId) {
            $ajanlat = Ajanlat::firstOrCreate(
                ['munkalap_id' => $munkalapId],
                ['statusz' => 'tervezet', 'osszeg_brutto' => 0, 'letrehozva' => now()]
            );

            $oldStatus = $ajanlat->statusz;
            if (isset($data['megjegyzes'])) $ajanlat->megjegyzes = $data['megjegyzes'];
            if (isset($data['statusz'])) $ajanlat->statusz = $data['statusz'];
            $ajanlat->save();

            // Készletkezelés tételekre
            if (isset($data['tetelek'])) {
                $hasTipus = Schema::hasColumn('munkalap_ajanlat_tetelek', 'tipus');
                $hasPart = Schema::hasColumn('munkalap_ajanlat_tetelek', 'alkatresz_id');
                $hasNetto = Schema::hasColumn('munkalap_ajanlat_tetelek', 'netto_egyseg_ar');
                $hasBrutto = Schema::hasColumn('munkalap_ajanlat_tetelek', 'brutto_egyseg_ar');
                $hasAfa = Schema::hasColumn('munkalap_ajanlat_tetelek', 'afa_kulcs');

                $aid = $ajanlat->ID ?? $ajanlat->id ?? $ajanlat->getKey();
                $oldItems = AjanlatTetel::where('ajanlat_id', $aid)->get();
                $hadRows = $oldItems->count() > 0;
                $oldParts = [];
                if ($hasPart) {
                    foreach ($oldItems as $oi) {
                        $pid = $oi->getAttribute('alkatresz_id');
                        if ($pid) {
                            $oldParts[$pid] = ($oldParts[$pid] ?? 0) + (float)$oi->mennyiseg;
                        }
                    }
                }
                $newParts = [];
                foreach ($data['tetelek'] as $t) {
                    if (!empty($t['alkatresz_id'])) {
                        $pid = (int)$t['alkatresz_id'];
                        $newParts[$pid] = ($newParts[$pid] ?? 0) + (float)$t['mennyiseg'];
                    }
                }
                // Foglalás készletből
                if ($hasPart) {
                    $allIds = array_unique(array_merge(array_keys($oldParts), array_keys($newParts)));
                    foreach ($allIds as $pid) {
                        $prev = $oldParts[$pid] ?? 0.0;
                        $next = $newParts[$pid] ?? 0.0;
                        $delta = $next - $prev;
                        if ($delta > 0) DB::table('alkatreszek')->where('ID', $pid)->decrement('keszlet', $delta);
                        elseif ($delta < 0) DB::table('alkatreszek')->where('ID', $pid)->increment('keszlet', -$delta);
                    }
                } else {            
                    if (!$hadRows && !empty($newParts)) {
                        foreach ($newParts as $pid => $qty) {
                            DB::table('alkatreszek')->where('ID', $pid)->decrement('keszlet', $qty);
                        }
                    }
                }

                AjanlatTetel::where('ajanlat_id', $aid)->delete();
                $sumBrutto = 0;
                foreach ($data['tetelek'] as $t) {
                    $vat = isset($t['afa_kulcs']) ? (float)$t['afa_kulcs'] : 27.0;
                    $netto = isset($t['netto_egyseg_ar']) ? (float)$t['netto_egyseg_ar'] : null;
                    $brutto = isset($t['brutto_egyseg_ar']) ? (float)$t['brutto_egyseg_ar'] : null;
                    if ($netto === null && $brutto !== null) { $netto = $brutto / (1 + $vat/100.0); }
                    if ($brutto === null && $netto !== null) { $brutto = $netto * (1 + $vat/100.0); }
                    $menny = (float)$t['mennyiseg'];
                    $osszegBrutto = $menny * ($brutto ?? 0);
                    $sumBrutto += $osszegBrutto;
                    $payload = [
                        'ajanlat_id' => $aid,
                        'megnevezes' => $t['megnevezes'],
                        'mennyiseg' => $menny,
                        'egyseg_ar' => $netto ?? 0,
                        'osszeg' => $osszegBrutto,
                    ];
                    if ($hasTipus) { $payload['tipus'] = $t['tipus'] ?? 'egyedi'; }
                    if ($hasPart) { $payload['alkatresz_id'] = $t['alkatresz_id'] ?? null; }
                    if ($hasNetto) { $payload['netto_egyseg_ar'] = $netto ?? 0; }
                    if ($hasBrutto) { $payload['brutto_egyseg_ar'] = $brutto ?? 0; }
                    if ($hasAfa) { $payload['afa_kulcs'] = $vat; }
                    AjanlatTetel::create($payload);
                }
                $ajanlat->osszeg_brutto = $sumBrutto;
                $ajanlat->save();
            }

            // Készletváltoztatás státusz módosítás esetén
            if (isset($data['statusz'])) {
                $newStatus = $data['statusz'];
                if ($oldStatus !== 'elutasitva' && $newStatus === 'elutasitva') {
                    $hasPart = Schema::hasColumn('munkalap_ajanlat_tetelek', 'alkatresz_id');
                    if ($hasPart) {
                        $rows = AjanlatTetel::where('ajanlat_id', $ajanlat->id)->get();
                        foreach ($rows as $r) {
                            $pid = $r->getAttribute('alkatresz_id');
                            if ($pid) {
                                DB::table('alkatreszek')->where('ID', $pid)->increment('keszlet', (float)$r->mennyiseg);
                            }
                        }
                    }
                }
            }

            $aid = $ajanlat->ID ?? $ajanlat->id ?? $ajanlat->getKey();
            $fresh = Ajanlat::find($aid);
            $fk = Schema::hasColumn('munkalap_ajanlat_tetelek','ajanlat_id') ? 'ajanlat_id' : (Schema::hasColumn('munkalap_ajanlat_tetelek','AjanlatID') ? 'AjanlatID' : (Schema::hasColumn('munkalap_ajanlat_tetelek','ajanlatId') ? 'ajanlatId' : 'ajanlat_id'));
            $rows = DB::table('munkalap_ajanlat_tetelek')->where($fk, $aid)->get();
            if ($rows instanceof \Illuminate\Support\Collection) {
                $rows = $rows->map(function ($r) {
                    if (empty($r->tipus) && (!empty($r->alkatresz_id) || !empty($r->AlkatreszID) || !empty($r->alkatreszId))) {
                        $r->tipus = 'alkatresz';
                    }
                    return $r;
                });
            }
            $isEmpty = ($rows instanceof \Illuminate\Support\Collection) ? $rows->isEmpty() : (empty($rows));
            if ($isEmpty) {
                $altFk = Schema::hasColumn('munkalap_ajanlat_tetelek','munkalap_id') ? 'munkalap_id' : (Schema::hasColumn('munkalap_ajanlat_tetelek','MunkalapID') ? 'MunkalapID' : (Schema::hasColumn('munkalap_ajanlat_tetelek','munkalapId') ? 'munkalapId' : null));
                if ($altFk) { $rows = DB::table('munkalap_ajanlat_tetelek')->where($altFk, $munkalapId)->get(); }
                if ($rows instanceof \Illuminate\Support\Collection) {
                    $rows = $rows->map(function ($r) {
                        if (empty($r->tipus) && (!empty($r->alkatresz_id) || !empty($r->AlkatreszID) || !empty($r->alkatreszId))) {
                            $r->tipus = 'alkatresz';
                        }
                        return $r;
                    });
                }
            }
            $fresh->setRelation('tetelek', $rows);
            return $fresh;
        });

        // Ügyfél értesítése
        try {
            $ml = \App\Models\Munkalap::with('Ugyfel')->find($munkalapId);
            if ($ml && $ml->Ugyfel) {
                $ml->Ugyfel->notify(new OfferUpdated($ml->ID, $data['statusz'] ?? null));
            }
        } catch (\Throwable $e) {}
        return response()->json($result);
    }

    /**
     * Ügyfél által árajánlat jóváhagyva
     */
    public function customerAccept(Request $request, $munkalapId)
    {
        $auth = $request->user();
        if (!$auth) abort(401);
        $munkalap = \App\Models\Munkalap::findOrFail($munkalapId);
        if ($auth->jogosultsag === 'Ugyfel' && (int)$munkalap->user_id !== (int)$auth->getKey()) {
            abort(403, 'Nincs jogosultság');
        }
        $ajanlat = Ajanlat::firstOrCreate(['munkalap_id' => $munkalapId], [
            'statusz' => 'tervezet', 'osszeg_brutto' => 0, 'letrehozva' => now()
        ]);
        $ajanlat->statusz = 'elfogadva';
        $ajanlat->save();
        // Munkalap státusz frissítése
        try { $munkalap->statusz = 'ajanlat_elfogadva'; $munkalap->save(); } catch (\Throwable $e) {}
        return response()->json(Ajanlat::with('tetelek')->find($ajanlat->getKey()));
    }

    /**
     * Ügyfél eutasítja az árajánlatot
     */
    public function customerReject(Request $request, $munkalapId)
    {
        $auth = $request->user();
        if (!$auth) abort(401);
        $munkalap = \App\Models\Munkalap::findOrFail($munkalapId);
        if ($auth->jogosultsag === 'Ugyfel' && (int)$munkalap->user_id !== (int)$auth->getKey()) {
            abort(403, 'Nincs jogosultsĂˇg');
        }
        $ajanlat = Ajanlat::firstOrCreate(['munkalap_id' => $munkalapId], [
            'statusz' => 'tervezet', 'osszeg_brutto' => 0, 'letrehozva' => now()
        ]);
        // Újra készletbe vételezés tételekre
        $hasPart = \Illuminate\Support\Facades\Schema::hasColumn('munkalap_ajanlat_tetelek', 'alkatresz_id');
        if ($hasPart) {
            $rows = AjanlatTetel::where('ajanlat_id', $ajanlat->id)->get();
            foreach ($rows as $r) {
                $pid = $r->getAttribute('alkatresz_id');
                if ($pid) {
                    DB::table('alkatreszek')->where('ID', $pid)->increment('keszlet', (float)$r->mennyiseg);
                }
            }
        }
        $ajanlat->statusz = 'elutasitva';
        $ajanlat->save();
        // Munkalap státusz frissítése
        try { $munkalap->statusz = 'ajanlat_elutasitva'; $munkalap->save(); } catch (\Throwable $e) {}
        return response()->json(Ajanlat::with('tetelek')->find($ajanlat->getKey()));
    }
}
