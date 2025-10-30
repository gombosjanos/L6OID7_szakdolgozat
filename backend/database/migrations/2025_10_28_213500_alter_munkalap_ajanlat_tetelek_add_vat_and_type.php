<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('munkalap_ajanlat_tetelek', function (Blueprint $table) {
            if (!Schema::hasColumn('munkalap_ajanlat_tetelek', 'tipus')) {
                $table->string('tipus', 20)->default('egyedi');
            }
            if (!Schema::hasColumn('munkalap_ajanlat_tetelek', 'alkatresz_id')) {
                $table->unsignedBigInteger('alkatresz_id')->nullable();
            }
            if (!Schema::hasColumn('munkalap_ajanlat_tetelek', 'netto_egyseg_ar')) {
                $table->decimal('netto_egyseg_ar', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('munkalap_ajanlat_tetelek', 'brutto_egyseg_ar')) {
                $table->decimal('brutto_egyseg_ar', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('munkalap_ajanlat_tetelek', 'afa_kulcs')) {
                $table->decimal('afa_kulcs', 5, 2)->default(27.00);
            }
        });
    }

    public function down(): void
    {
        Schema::table('munkalap_ajanlat_tetelek', function (Blueprint $table) {
            // Optional: keep columns on rollback to avoid data loss
        });
    }
};

