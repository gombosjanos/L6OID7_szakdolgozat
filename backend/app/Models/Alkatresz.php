<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alkatresz extends Model
{
    protected $table = 'alkatreszek';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'gyarto', 'alkatresznev', 'a_cikkszam', 'nettoar', 'bruttoar', 'keszlet'
    ];
}
