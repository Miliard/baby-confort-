<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos que se mandan seguido por WhatsApp y no pertenecen al catálogo.
 *
 * Las típicas: el producto ya puesto, cómo viene el empaque abierto, una foto
 * del reparto. Son material de venta, no fichas de producto, así que viven acá
 * y no en la tienda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_fotos', function (Blueprint $table) {
            $table->id();

            // Lo que se lee al elegirla. Corto: "Talla M puesta", "Empaque abierto".
            $table->string('titulo');

            // Texto que va debajo de la foto cuando se manda. Puede ir vacío.
            $table->text('pie')->nullable();

            $table->string('ruta');

            $table->integer('orden')->default(0);
            $table->boolean('activa')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_fotos');
    }
};
