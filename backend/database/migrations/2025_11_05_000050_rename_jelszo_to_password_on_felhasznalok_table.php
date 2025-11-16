<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('felhasznalok', 'password')) {
            if (Schema::hasColumn('felhasznalok', 'jelszo')) {
                DB::statement('UPDATE felhasznalok SET `password` = COALESCE(`password`, `jelszo`)');
                Schema::table('felhasznalok', function (Blueprint $table) {
                    $table->dropColumn('jelszo');
                });
            }
            return;
        }
        Schema::table('felhasznalok', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });

        if (Schema::hasColumn('felhasznalok', 'jelszo')) {
            DB::statement('UPDATE felhasznalok SET `password` = `jelszo` WHERE `password` IS NULL AND `jelszo` IS NOT NULL');
            Schema::table('felhasznalok', function (Blueprint $table) {
                $table->dropColumn('jelszo');
            });
        }
        DB::statement('ALTER TABLE felhasznalok MODIFY `password` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('felhasznalok', 'jelszo')) {
            Schema::table('felhasznalok', function (Blueprint $table) {
                $table->string('jelszo')->nullable()->after('email');
            });
        }

        if (Schema::hasColumn('felhasznalok', 'password')) {
            DB::statement('UPDATE felhasznalok SET `jelszo` = COALESCE(`jelszo`, `password`)');
        }
    }
};

