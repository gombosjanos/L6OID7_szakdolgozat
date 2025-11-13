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
        'jelszo',
        'telefonszam',
        'jogosultsag',
        'ketfaktor_secret',
        'ketfaktor_enabled_at',
        'ketfaktor_recovery_kodok',
    ];

    protected $hidden = [
        'password',
        'jelszo',
        'ketfaktor_secret',
        'ketfaktor_recovery_kodok',
    ];

    protected $casts = [
        'ketfaktor_enabled_at' => 'datetime',
    ];

    protected $appends = ['ketfaktor_aktiv'];

    public function getAuthPassword()
    {
        return $this->attributes['password'] ?? null;
    }

    public function getPasswordAttribute(): ?string
    {
        return $this->attributes['password'] ?? null;
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = $value;
    }

    public function getJelszoAttribute(): ?string
    {
        return $this->attributes['password'] ?? null;
    }

    public function setJelszoAttribute($value): void
    {
        $this->attributes['password'] = $value;
    }

    public function getKetfaktorAktivAttribute(): bool
    {
        return (bool) $this->ketfaktor_enabled_at;
    }
}
