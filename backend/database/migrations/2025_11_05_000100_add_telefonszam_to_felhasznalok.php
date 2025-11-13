<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Telefonszám mező hozzáadása a felhasználókhoz (E.164, egyediség).
     */
    public function up(): void
    {
        if (Schema::hasColumn('felhasznalok', 'telefonszam')) {
            return;
        }

        Schema::table('felhasznalok', function (Blueprint $table) {
            $table->string('telefonszam', 20)
                ->after('email')
                ->unique();
        });
    }

    /**
     * Telefonszám mező eltávolĂ­tása visszagörgetéskor.
     */
    public function down(): void
    {
        Schema::table('felhasznalok', function (Blueprint $table) {
            if (Schema::hasColumn('felhasznalok', 'telefonszam')) {
                if ($this->indexExists('felhasznalok', 'felhasznalok_telefonszam_unique')) {
                    $table->dropUnique('felhasznalok_telefonszam_unique');
                }
                $table->dropColumn('telefonszam');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $prefixedTable = $connection->getTablePrefix() . $table;
        $result = $connection->select("SHOW INDEX FROM `{$prefixedTable}` WHERE Key_name = ?", [$index]);
        return !empty($result);
    }
};


