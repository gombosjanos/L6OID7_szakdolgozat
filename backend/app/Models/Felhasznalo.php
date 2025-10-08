<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Felhasznalo extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'felhasznalok';
    protected $primaryKey = 'id';   // <-- ez itt legyen
    public $timestamps = false;

    protected $fillable = [
        'nev',
        'felhasznalonev',
        'email',
        'telefonszam',
        'jogosultsag',
        'jelszo'
    ];


    protected $hidden = [
        'jelszo',
    ];

    public function getAuthPassword()
    {
        return $this->jelszo;
    }
}
use App\Models\Felhasznalo;
use Illuminate\Support\Facades\Hash;

