<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Készlet oszlop hozzáadása az alkatreszek táblához.
     */
    public function up(): void
    {
        Schema::table('alkatreszek', function (Blueprint $table) {
            $table->integer('keszlet')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('alkatreszek', function (Blueprint $table) {
            $table->dropColumn('keszlet');
        });
    }
};
