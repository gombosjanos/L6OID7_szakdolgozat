<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JavitandoGep extends Model
{
    protected $table = 'javitando_gepek';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'gep_id', 'tulajdonos_id', 'sorozatszam'
    ];

    public function tulajdonos()
    {
        return $this->belongsTo(Felhasznalo::class, 'tulajdonos_id', 'ID');
    }

    public function gep()
    {
        return $this->belongsTo(Gep::class, 'gep_id', 'ID');
    }
}
