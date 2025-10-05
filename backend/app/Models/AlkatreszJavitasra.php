<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlkatreszJavitasra extends Model
{
    protected $table = 'alkatreszek_javitasra';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'munkalap_id', 'alkatresz_id', 'mennyiseg'
    ];

    public function munkalap()
    {
        return $this->belongsTo(Munkalap::class, 'munkalap_id', 'ID');
    }

    public function alkatresz()
    {
        return $this->belongsTo(Alkatresz::class, 'alkatresz_id', 'ID');
    }
}
