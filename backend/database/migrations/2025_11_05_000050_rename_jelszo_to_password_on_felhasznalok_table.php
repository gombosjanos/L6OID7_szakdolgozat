<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ha már létezik a password oszlop, nincs teendő
        if (Schema::hasColumn('felhasznalok', 'password')) {
            // Ha még a régi jelszo oszlop is létezik, migráljuk és eldobjuk
            if (Schema::hasColumn('felhasznalok', 'jelszo')) {
                DB::statement('UPDATE felhasznalok SET `password` = COALESCE(`password`, `jelszo`)');
                Schema::table('felhasznalok', function (Blueprint $table) {
                    $table->dropColumn('jelszo');
                });
            }
            return;
        }

        // Ha nincs password oszlop, vegyük fel és másoljuk át a jelszo értékét majd dobjuk a régit
        Schema::table('felhasznalok', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });

        if (Schema::hasColumn('felhasznalok', 'jelszo')) {
            DB::statement('UPDATE felhasznalok SET `password` = `jelszo` WHERE `password` IS NULL AND `jelszo` IS NOT NULL');
            Schema::table('felhasznalok', function (Blueprint $table) {
                $table->dropColumn('jelszo');
            });
        }

        // Végül tegyük NOT NULL-lá (ha üres lenne, hagyjuk NULL-on és későbbi seeder javítja)
        DB::statement('ALTER TABLE felhasznalok MODIFY `password` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Visszaalakítás: hozzuk vissza a jelszo oszlopot és másoljuk vissza a password-öt
        if (!Schema::hasColumn('felhasznalok', 'jelszo')) {
            Schema::table('felhasznalok', function (Blueprint $table) {
                $table->string('jelszo')->nullable()->after('email');
            });
        }

        if (Schema::hasColumn('felhasznalok', 'password')) {
            DB::statement('UPDATE felhasznalok SET `jelszo` = COALESCE(`jelszo`, `password`)');
        }

        // Nem dobjuk el automatikusan a password-öt down esetén, hogy ne vesszen adat
    }
};

