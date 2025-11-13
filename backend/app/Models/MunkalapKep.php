<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MunkalapKep extends Model
{
    protected $table = 'munkalap_kepek';
    public $timestamps = false;

    protected $fillable = [
        'munkalap_id',
        'fajlnev',
        'eredeti_nev',
        'mime',
        'meret',
        'feltolto_id',
        'letrehozva',
    ];

    protected $casts = [
        'letrehozva' => 'datetime',
    ];

    protected $appends = ['url'];

    public function munkalap()
    {
        return $this->belongsTo(Munkalap::class, 'munkalap_id', 'ID');
    }

    public function feltolto()
    {
        return $this->belongsTo(Felhasznalo::class, 'feltolto_id', 'id');
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->id || !$this->munkalap_id) {
            return null;
        }
        // Return API route so the frontend dev server proxy (/api -> backend) can handle it
        return route('munkalapkepek.show', ['id' => $this->munkalap_id, 'kep' => $this->id], false);
    }
}
