<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias fotos por respuesta rápida, no una sola.
 *
 * Hay respuestas que necesitan más de una imagen de golpe: los tres tamaños
 * del paquete, las fotos de cómo queda puesto, los pasos para pagar. Con una
 * sola había que mandar la respuesta y después buscar el resto a mano.
 *
 * Se guarda la lista de rutas en `imagenes`. La columna vieja `imagen` se
 * queda y siempre lleva la primera, así nada de lo que ya la lee se rompe.
 * Las respuestas que ya tenían foto pasan a tenerla como primera de la lista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('respuestas_rapidas')) return;

        if (! Schema::hasColumn('respuestas_rapidas', 'imagenes')) {
            Schema::table('respuestas_rapidas', function (Blueprint $t) {
                $t->text('imagenes')->nullable()->after('imagen');
            });
        }

        if (! Schema::hasColumn('respuestas_rapidas', 'imagen')) return;

        DB::table('respuestas_rapidas')
            ->whereNotNull('imagen')
            ->where('imagen', '!=', '')
            ->whereNull('imagenes')
            ->orderBy('id')
            ->each(function ($r) {
                DB::table('respuestas_rapidas')
                    ->where('id', $r->id)
                    ->update(['imagenes' => json_encode([$r->imagen])]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('respuestas_rapidas')) return;
        if (! Schema::hasColumn('respuestas_rapidas', 'imagenes')) return;

        Schema::table('respuestas_rapidas', function (Blueprint $t) {
            $t->dropColumn('imagenes');
        });
    }
};
