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

    public function munkalapok()
    {
        return $this->hasMany(Munkalap::class, 'gep_id', 'ID');
    }
}
