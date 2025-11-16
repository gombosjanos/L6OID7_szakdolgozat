<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Felhasználók táblája: belépéshez és jogosultságokhoz szükséges törzsadatok.
     * Megjegyzés: a jelszó hash a Laravel konvenció szerint a "password" oszlopba kerül.
     */
    public function up(): void
    {
        Schema::create('felhasznalok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nev', 50);
            $table->string('felhasznalonev', 50)->nullable();
            $table->string('email', 100)->unique();
            // A jelszó hash tárolása
            $table->string('password');
            $table->string('telefonszam', 20)->nullable();
            $table->string('jogosultsag', 20)->default('Ugyfel');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('felhasznalok');
    }
};
