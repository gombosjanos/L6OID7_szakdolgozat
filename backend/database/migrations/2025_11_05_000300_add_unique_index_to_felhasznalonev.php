<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensures the felhasznalonev column is populated and unique.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::table('felhasznalok')
                ->whereNull('felhasznalonev')
                ->orWhere('felhasznalonev', '')
                ->update(['felhasznalonev' => DB::raw("'user' || id")]);
        } else {
            DB::statement("UPDATE felhasznalok SET felhasznalonev = CONCAT('user', id) WHERE felhasznalonev IS NULL OR felhasznalonev = ''");
            DB::statement('ALTER TABLE felhasznalok MODIFY felhasznalonev VARCHAR(50) NOT NULL');
        }

        if (! $this->indexExists('felhasznalok', 'felhasznalok_felhasznalonev_unique')) {
            Schema::table('felhasznalok', function (Blueprint $table) {
                $table->unique('felhasznalonev', 'felhasznalok_felhasznalonev_unique');
            });
        }
    }

    /**
     * Drops the uniqueness constraint when rolling back.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE felhasznalok MODIFY felhasznalonev VARCHAR(50) NULL');
        }

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
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $list = $connection->select("PRAGMA index_list('{$prefixedTable}')");
            foreach ($list as $idx) {
                if (($idx->name ?? null) === $index) {
                    return true;
                }
            }
            return false;
        }

        $result = $connection->select("SHOW INDEX FROM `{$prefixedTable}` WHERE Key_name = ?", [$index]);
        return !empty($result);
    }
};
