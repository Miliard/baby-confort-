<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los textos que se repiten todo el día: precios, formas de pago, cobertura de
 * envío, horarios. Van en la base y no en el código para que se puedan crear y
 * corregir desde el panel, sin tener que pedirle nada a nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_rapidas', function (Blueprint $table) {
            $table->id();

            // Lo que se lee en el botón. Corto, para que entre.
            $table->string('titulo');

            // El mensaje que se le manda al cliente.
            $table->text('texto');

            // Para ordenarlas: las más usadas primero.
            $table->integer('orden')->default(0);

            // Apagarla sin borrarla, por si es de temporada.
            $table->boolean('activa')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_rapidas');
    }
};
