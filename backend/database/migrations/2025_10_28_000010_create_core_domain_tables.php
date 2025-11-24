<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gepek')) {
            Schema::create('gepek', function (Blueprint $table) {
                $table->bigIncrements('ID');
                $table->string('gyarto', 50);
                $table->string('tipusnev', 255);
                $table->string('g_cikkszam', 50);
                $table->integer('gyartasiev')->default(0);
            });
        }

        if (!Schema::hasTable('alkatreszek')) {
            Schema::create('alkatreszek', function (Blueprint $table) {
                $table->bigIncrements('ID');
                $table->string('alkatresznev', 255);
                $table->string('a_cikkszam', 50);
                $table->decimal('nettoar', 10, 2)->default(0);
                $table->decimal('bruttoar', 10, 2)->default(0);
                $table->integer('keszlet')->nullable();
            });
        }

        if (!Schema::hasTable('munkalapok')) {
            Schema::create('munkalapok', function (Blueprint $table) {
                $table->bigIncrements('ID');
                $table->string('munkalapsorsz', 30);
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('gep_id');
                $table->text('hibaleiras')->default('');
                $table->text('megjegyzes')->default('');
                $table->string('statusz', 50)->default('uj');
                $table->timestamp('letrehozva')->useCurrent();
                $table->unsignedBigInteger('letrehozta')->nullable();

                $table->index('user_id');
                $table->index('gep_id');
                $table->foreign('user_id')->references('id')->on('felhasznalok')->onDelete('cascade');
                $table->foreign('gep_id')->references('ID')->on('gepek')->onDelete('cascade');
                $table->foreign('letrehozta')->references('id')->on('felhasznalok')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('munkalapok');
        Schema::dropIfExists('alkatreszek');
        Schema::dropIfExists('gepek');
    }
};
