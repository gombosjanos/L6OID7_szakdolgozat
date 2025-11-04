<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjanlatTetel extends Model
{
    protected $table = 'munkalap_ajanlat_tetelek';
    public $timestamps = false;

    protected $fillable = [
        'ajanlat_id',
        'tipus',
        'alkatresz_id',
        'megnevezes',
        'mennyiseg',
        'egyseg_ar',
        'osszeg',
        'netto_egyseg_ar',
        'brutto_egyseg_ar',
        'afa_kulcs',
    ];

    public function ajanlat()
    {
        return $this->belongsTo(Ajanlat::class, 'ajanlat_id', 'id');
    }
}
