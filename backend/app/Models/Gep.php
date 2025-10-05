<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gep extends Model
{
    protected $table = 'gepek';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'gyarto', 'tipusnev', 'g_cikkszam', 'gyartasiev'
    ];

    public function javitandoGepek()
    {
        return $this->hasMany(JavitandoGep::class, 'gep_id', 'ID');
    }

    public function munkalapok()
    {
        return $this->hasMany(Munkalap::class, 'gep_id', 'ID');
    }
}
