<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ficha del asistente de ventas: una por conversación.
 *
 * Guarda en qué paso va, lo que ya se sabe del pedido (talla, tipo, carrito,
 * municipio, datos) y si el asistente está encendido o le pasó el chat a Wil.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asistente_fichas')) return;

        Schema::create('asistente_fichas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversacion_id')->unique();

            // listo · activo · wil · apagado · terminado
            $table->string('estado', 20)->default('listo');
            $table->string('paso', 30)->nullable();
            $table->json('datos')->nullable();

            // Por qué se lo pasó a Wil ("pidió rebaja", "no entendí el municipio").
            $table->string('motivo', 190)->nullable();

            // El último mensaje del cliente que ya se leyó, para no leerlo dos veces.
            $table->unsignedBigInteger('ultimo_mensaje_id')->nullable();

            // Lo que Wil haya escrito ANTES de esta hora no lo frena: sirve para
            // "Reiniciar prueba" y para volver a encenderlo en un chat.
            $table->timestamp('desde')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistente_fichas');
    }
};
