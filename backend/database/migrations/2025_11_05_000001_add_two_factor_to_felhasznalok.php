<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kétfaktoros azonosítás oszlopai a felhasználók táblán:
     * - ketfaktor_secret, ketfaktor_enabled_at, ketfaktor_recovery_kodok
     */
    public function up(): void
    {
        Schema::table('felhasznalok', function (Blueprint $table) {
            if (!Schema::hasColumn('felhasznalok', 'ketfaktor_secret')) {
                $table->text('ketfaktor_secret')->nullable()->after('password');
            }

            if (!Schema::hasColumn('felhasznalok', 'ketfaktor_enabled_at')) {
                $table->timestamp('ketfaktor_enabled_at')->nullable()->after('ketfaktor_secret');
            }

            if (!Schema::hasColumn('felhasznalok', 'ketfaktor_recovery_kodok')) {
                $table->text('ketfaktor_recovery_kodok')->nullable()->after('ketfaktor_enabled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('felhasznalok', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('felhasznalok', 'ketfaktor_secret') ? 'ketfaktor_secret' : null,
                Schema::hasColumn('felhasznalok', 'ketfaktor_enabled_at') ? 'ketfaktor_enabled_at' : null,
                Schema::hasColumn('felhasznalok', 'ketfaktor_recovery_kodok') ? 'ketfaktor_recovery_kodok' : null,
            ]);

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
