<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('felhasznalok')
            ->select('telefonszam')
            ->whereNotNull('telefonszam')
            ->groupBy('telefonszam')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('telefonszam');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Ismétlődő telefonszámok találhatók, előbb rendezni kell őket: ' . $duplicates->implode(', ')
            );
        }

        if ($this->indexExists('felhasznalok', 'felhasznalok_telefonszam_unique')) {
            return;
        }

        Schema::table('felhasznalok', function (Blueprint $table) {
            $table->unique('telefonszam');
        });
    }

    public function down(): void
    {
        Schema::table('felhasznalok', function (Blueprint $table) {
            if ($this->indexExists('felhasznalok', 'felhasznalok_telefonszam_unique')) {
                $table->dropUnique('felhasznalok_telefonszam_unique');
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



