<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MunkalapNaplo extends Model
{
    protected $table = 'munkalap_naplo';
    public $timestamps = false;

    protected $fillable = [
        'munkalap_id', 'tipus', 'uzenet', 'letrehozva'
    ];

    public function munkalap()
    {
        return $this->belongsTo(Munkalap::class, 'munkalap_id', 'ID');
    }
}

