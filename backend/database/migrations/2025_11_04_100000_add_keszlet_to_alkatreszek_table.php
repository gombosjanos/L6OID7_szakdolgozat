<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the stock column to the alkatreszek table when missing.
     */
    public function up(): void
    {
        Schema::table('alkatreszek', function (Blueprint $table) {
            if (! Schema::hasColumn('alkatreszek', 'keszlet')) {
                $table->integer('keszlet')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('alkatreszek', function (Blueprint $table) {
            if (Schema::hasColumn('alkatreszek', 'keszlet')) {
                $table->dropColumn('keszlet');
            }
        });
    }
};
