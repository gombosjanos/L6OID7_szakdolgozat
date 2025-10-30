<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alkatresz extends Model
{
    protected $table = 'alkatreszek';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'gyarto', 'alkatresznev', 'a_cikkszam', 'nettoar', 'bruttoar'
    ];

    public function javitasok()
    {
        return $this->hasMany(AlkatreszJavitasra::class, 'alkatresz_id', 'ID');
    }
}
