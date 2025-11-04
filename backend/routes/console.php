<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Alkatresz;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('parts:import {path : CSV file path} {--delimiter=; : CSV delimiter} {--vat=27 : VAT percent}', function () {
    $path = $this->argument('path');
    $delim = $this->option('delimiter') ?? ';';
    $vat = (float)($this->option('vat') ?? 27);

    if (!file_exists($path)) {
        $this->error("File not found: {$path}");
        return 1;
    }

    $fh = fopen($path, 'r');
    if (!$fh) {
        $this->error('Cannot open file');
        return 1;
    }

    $norm = function ($s) {
        $s = trim((string)$s);
        // normalize encoding to UTF-8
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1250,Windows-1252,ISO-8859-2,ISO-8859-1');
        }
        $s = mb_strtolower($s);
        $map = [
            'Ăˇ'=>'a','Ă©'=>'e','Ă­'=>'i','Ăł'=>'o','Ă¶'=>'o','Ĺ‘'=>'o','Ăş'=>'u','ĂĽ'=>'u','Ĺ±'=>'u',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/','_', $s);
        return trim($s,'_');
    };

    $header = fgetcsv($fh, 0, $delim);
    if (!$header) { $this->error('Empty CSV'); return 1; }
    $keys = array_map($norm, $header);

    // find column indexes
    $idx = function ($candidates) use ($keys) {
        foreach ((array)$candidates as $c) {
            $i = array_search($c, $keys, true);
            if ($i !== false) return $i;
        }
        return null;
    };

    $iCikkszam = $idx(['cikkszam','a_cikkszam','sku']);
    $iNev = $idx(['megnevezes','termeknev','nev','alkatresznev','alkatresz_megnevezes']);
    $iGyarto = $idx(['gyarto','marka','brand']);
    $iNetto = $idx(['netto','netto_ar','netto_egyseg_ar']);
    $iBrutto = $idx(['brutto','brutto_ar','brutto_egyseg_ar','ar']);

    if ($iCikkszam === null || $iNev === null) {
        $this->error('Required columns not found (cikkszam, megnevezes)');
        return 1;
    }

    $count = 0; $created = 0; $updated = 0;
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        if (count($row) === 1 && $row[0] === null) continue;
        $cikkszam = (string)($row[$iCikkszam] ?? '');
        $megnev = (string)($row[$iNev] ?? '');
        $gyarto = $iGyarto !== null ? (string)$row[$iGyarto] : null;
        $net = $iNetto !== null ? (float)str_replace([',',' '], ['.',''], (string)$row[$iNetto]) : null;
        $brut = $iBrutto !== null ? (float)str_replace([',',' '], ['.',''], (string)$row[$iBrutto]) : null;
        if ($cikkszam === '' && $megnev === '') continue;
        if ($net === null && $brut !== null) $net = $brut / (1 + $vat/100.0);
        if ($brut === null && $net !== null) $brut = $net * (1 + $vat/100.0);
        $net = $net ?? 0; $brut = $brut ?? 0;

        $part = Alkatresz::firstOrNew(['a_cikkszam' => $cikkszam]);
        $isNew = !$part->exists;
        $part->alkatresznev = $megnev;
        if ($gyarto !== null) $part->gyarto = $gyarto;
        $part->nettoar = round($net, 2);
        $part->bruttoar = round($brut, 2);
        $part->save();
        $isNew ? $created++ : $updated++;
        $count++;
    }
    fclose($fh);
    $this->info("Imported {$count} rows ({$created} created, {$updated} updated)");
    return 0;
})->purpose('Import parts CSV into alkatreszek table');
