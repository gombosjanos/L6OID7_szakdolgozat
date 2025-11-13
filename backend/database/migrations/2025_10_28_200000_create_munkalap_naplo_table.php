<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Munkalap napló: státusz változások és megjegyzések időbélyeggel.
    public function up(): void
    {
        Schema::create('munkalap_naplo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('munkalap_id');
            $table->string('tipus', 30); // statusz | jegyzet
            $table->text('uzenet');
            $table->timestamp('letrehozva')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('munkalap_naplo');
    }
};
