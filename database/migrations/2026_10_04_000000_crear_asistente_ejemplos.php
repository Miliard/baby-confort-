<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El banco de ejemplos del asistente: pedacitos de tus conversaciones reales
 * ("la clienta dijo esto → Wil contestó así"), sin datos personales.
 *
 * En cada turno se buscan los más parecidos a lo que escribió la clienta y se
 * le pasan a la IA, para que conteste como contestás vos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asistente_ejemplos')) return;

        Schema::create('asistente_ejemplos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversacion_id')->nullable();

            // Lo último que dijo la tienda antes (para saber en qué parte iba).
            $table->text('antes')->nullable();
            // Lo que escribió la clienta.
            $table->text('cliente');
            // Lo que contestaste vos.
            $table->text('respuesta');

            // Las palabras de "antes" + "cliente", ya normalizadas, para buscar rápido.
            $table->text('palabras');

            $table->timestamp('fecha')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistente_ejemplos');
    }
};
