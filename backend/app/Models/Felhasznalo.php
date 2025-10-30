<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Felhasznalo extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'felhasznalok';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nev',
        'felhasznalonev',
        'email',
        'telefonszam',
        'jogosultsag',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }
}
