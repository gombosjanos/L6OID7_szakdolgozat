<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Árajánlat törzs és tétel táblák (összegek, státusz, tételek).
    public function up(): void
    {
        Schema::create('munkalap_ajanlat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('munkalap_id');
            $table->text('megjegyzes')->nullable();
            $table->string('statusz', 30)->default('tervezet');
            $table->decimal('osszeg_brutto', 12, 2)->default(0);
            $table->timestamp('letrehozva')->nullable();
        });

        Schema::create('munkalap_ajanlat_tetelek', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ajanlat_id');
            $table->string('megnevezes', 255);
            $table->decimal('mennyiseg', 10, 2)->default(1);
            $table->decimal('egyseg_ar', 12, 2)->default(0);
            $table->decimal('osszeg', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('munkalap_ajanlat_tetelek');
        Schema::dropIfExists('munkalap_ajanlat');
    }
};
