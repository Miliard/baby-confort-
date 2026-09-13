<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Para poder responder a un mensaje puntual, como en WhatsApp.
 *
 * Guarda el identificador que le dio Meta al mensaje citado. Se usa el de Meta
 * y no el nuestro porque es el que hay que mandarle a la API para que la cita
 * le aparezca al cliente en su teléfono.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Si un intento anterior alcanzó a crear la columna y se cortó después,
        // volver a correrla no puede reventar.
        if (Schema::hasColumn('wa_mensajes', 'responde_a')) return;

        // Sin índice a propósito: crearlo obliga a MySQL a reconstruir la tabla
        // y esa tabla se escribe todo el tiempo, así que la migración se
        // quedaba esperando a que no hubiera nadie escribiendo. Son pocas
        // filas y se busca por un campo ya indexado, no hace falta.
        Schema::table('wa_mensajes', function (Blueprint $table) {
            $table->string('responde_a')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('wa_mensajes', 'responde_a')) return;

        Schema::table('wa_mensajes', function (Blueprint $table) {
            $table->dropColumn('responde_a');
        });
    }
};
