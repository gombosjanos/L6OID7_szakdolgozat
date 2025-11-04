<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Munkalap extends Model
{
    protected $table = 'munkalapok';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'gep_id', 'javitando_id', 'hibaleiras', 'megjegyzes', 'statusz', 'letrehozva', 'munkalapsorsz'
    ];

    public function ugyfel()
    {
        return $this->belongsTo(Felhasznalo::class, 'user_id', 'id');
    }

    public function gep()
    {
        return $this->belongsTo(Gep::class, 'gep_id', 'ID');
    }

    public function javitando()
    {
        return $this->belongsTo(JavitandoGep::class, 'javitando_id', 'ID');
    }

    public function alkatreszek()
    {
        return $this->hasMany(AlkatreszJavitasra::class, 'munkalap_id', 'ID');
    }
}
