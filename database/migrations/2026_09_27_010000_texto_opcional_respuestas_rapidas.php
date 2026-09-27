<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El texto de una respuesta rápida deja de ser obligatorio.
 *
 * Hay respuestas que son solo fotos: el catálogo de una talla, cómo queda
 * puesto un producto. Obligar a escribirles un texto hacía que se les pusiera
 * cualquier cosa para poder guardarlas, y esa cosa salía de pie de la foto.
 *
 * Una respuesta sin texto Y sin fotos sigue sin poder guardarse: eso lo frena
 * el formulario, porque no mandaría nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('respuestas_rapidas')) return;

        Schema::table('respuestas_rapidas', function (Blueprint $t) {
            $t->text('texto')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No se vuelve a obligatorio: habría respuestas guardadas sin texto y
        // la base no dejaría cambiarla. Queda opcional.
    }
};
