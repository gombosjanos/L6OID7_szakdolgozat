<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ajanlat extends Model
{
    protected $table = 'munkalap_ajanlat';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'munkalap_id', 'megjegyzes', 'statusz', 'osszeg_brutto', 'letrehozva',
    ];

    public function tetelek()
    {
        return $this->hasMany(AjanlatTetel::class, 'ajanlat_id', 'ID');
    }
}
