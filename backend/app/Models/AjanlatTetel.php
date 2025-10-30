<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjanlatTetel extends Model
{
    protected $table = 'munkalap_ajanlat_tetelek';
    public $timestamps = false;

    protected $fillable = [
        'ajanlat_id', 'megnevezes', 'mennyiseg', 'egyseg_ar', 'osszeg',
    ];

    public function ajanlat()
    {
        return $this->belongsTo(Ajanlat::class, 'ajanlat_id', 'id');
    }
}

