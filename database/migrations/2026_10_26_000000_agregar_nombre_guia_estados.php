<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Destinatario, contenido y cobro de cada guía, tal como los tiene Express (para filtrar AIWIBI). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_estados') || Schema::hasColumn('guia_estados', 'nombre')) return;

        Schema::table('guia_estados', function (Blueprint $table) {
            $table->string('nombre', 190)->nullable()->after('guia');
            $table->string('contenido', 255)->nullable()->after('nombre');
            $table->decimal('cod', 10, 2)->nullable()->after('contenido');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('guia_estados', 'nombre')) {
            Schema::table('guia_estados', fn (Blueprint $t) => $t->dropColumn(['nombre', 'contenido', 'cod']));
        }
    }
};
