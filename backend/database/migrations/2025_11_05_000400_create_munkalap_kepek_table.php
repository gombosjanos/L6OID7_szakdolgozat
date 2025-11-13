<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Munkalapokhoz csatolt képek: fájlnév, MIME, méret, feltöltő és időbélyeg.
    public function up(): void
    {
        Schema::create('munkalap_kepek', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('munkalap_id');
            $table->string('fajlnev', 255);
            $table->string('eredeti_nev', 255);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('meret')->nullable();
            $table->unsignedBigInteger('feltolto_id')->nullable();
            $table->timestamp('letrehozva')->useCurrent();

            $table->foreign('munkalap_id')->references('ID')->on('munkalapok')->onDelete('cascade');
            $table->foreign('feltolto_id')->references('id')->on('felhasznalok')->onDelete('set null');
            $table->index('munkalap_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('munkalap_kepek');
    }
};
