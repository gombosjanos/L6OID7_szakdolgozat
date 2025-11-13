<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Felhasználónév egyediségének biztosĂ­tása.
     */
    public function up(): void
    {
        DB::statement("UPDATE felhasznalok SET felhasznalonev = CONCAT('user', id) WHERE felhasznalonev IS NULL OR felhasznalonev = ''");

        DB::statement('ALTER TABLE felhasznalok MODIFY felhasznalonev VARCHAR(50) NOT NULL');

        if (!$this->indexExists('felhasznalok', 'felhasznalok_felhasznalonev_unique')) {
            Schema::table('felhasznalok', function (Blueprint $table) {
                $table->unique('felhasznalonev', 'felhasznalok_felhasznalonev_unique');
            });
        }
    }

    /**
     * Egyediség eltávolĂ­tása visszagörgetéskor.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE felhasznalok MODIFY felhasznalonev VARCHAR(50) NULL');

        if ($this->indexExists('felhasznalok', 'felhasznalok_felhasznalonev_unique')) {
            Schema::table('felhasznalok', function (Blueprint $table) {
                $table->dropUnique('felhasznalok_felhasznalonev_unique');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $prefixedTable = $connection->getTablePrefix() . $table;
        $result = $connection->select("SHOW INDEX FROM `{$prefixedTable}` WHERE Key_name = ?", [$index]);
        return !empty($result);
    }
};


