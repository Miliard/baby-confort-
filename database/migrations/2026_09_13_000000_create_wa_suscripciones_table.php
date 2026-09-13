<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los teléfonos que quieren recibir avisos con la aplicación cerrada.
 *
 * Cada navegador de cada persona genera una dirección propia donde Google o
 * Apple le entregan los avisos. Eso es lo que se guarda acá. No es un dato
 * secreto ni identifica a nadie por sí solo, pero sí es por dispositivo: si
 * alguien usa el teléfono y la computadora, va a tener dos renglones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_suscripciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // La dirección donde el navegador espera los avisos. Es larga.
            $table->text('endpoint');

            // Huella corta del endpoint, para no repetir el mismo dispositivo.
            // Se usa esto y no el endpoint completo porque MySQL no deja poner
            // un índice único sobre una columna de texto largo.
            $table->string('huella', 64)->unique();

            // Claves del navegador. Hoy no se usan (los avisos van sin
            // contenido), pero se guardan por si más adelante hace falta
            // mandar el texto del mensaje adentro del aviso.
            $table->string('p256dh')->nullable();
            $table->string('auth')->nullable();

            $table->timestamp('ultimo_error_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_suscripciones');
    }
};
