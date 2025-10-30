<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('felhasznalok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nev', 50);
            $table->string('felhasznalonev', 50)->nullable();
            $table->string('email', 100)->unique();
            $table->string('jelszo');
            $table->string('telefonszam', 20)->nullable();
            $table->string('jogosultsag', 20)->default('ugyfel');
            // No timestamps as model sets public $timestamps = false
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('felhasznalok');
    }
};

