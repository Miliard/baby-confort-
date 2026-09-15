<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una foto opcional para cada respuesta rápida.
 *
 * Hay respuestas que sin foto no dicen nada: la tabla de tallas, cómo viene el
 * paquete, dónde queda el punto de entrega. Se explican mejor con una imagen
 * que con tres párrafos.
 *
 * Se guarda la ruta, igual que en wa_fotos: el archivo vive en el disco y acá
 * solo queda dónde encontrarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('respuestas_rapidas')) return;
        if (Schema::hasColumn('respuestas_rapidas', 'imagen')) return;

        Schema::table('respuestas_rapidas', function (Blueprint $t) {
            $t->string('imagen')->nullable()->after('texto');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('respuestas_rapidas')) return;
        if (! Schema::hasColumn('respuestas_rapidas', 'imagen')) return;

        Schema::table('respuestas_rapidas', function (Blueprint $t) {
            $t->dropColumn('imagen');
        });
    }
};
