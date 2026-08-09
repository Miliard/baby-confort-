<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $table) {
            // Qué lleva el paquete y cuánto se cobra al entregar (vienen del PDF).
            if (! Schema::hasColumn('guia_fotos', 'contenido')) {
                $table->text('contenido')->nullable()->after('telefono');
            }
            if (! Schema::hasColumn('guia_fotos', 'cobrar')) {
                $table->decimal('cobrar', 8, 2)->nullable()->after('contenido');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $table) {
            if (Schema::hasColumn('guia_fotos', 'contenido')) $table->dropColumn('contenido');
            if (Schema::hasColumn('guia_fotos', 'cobrar')) $table->dropColumn('cobrar');
        });
    }
};
