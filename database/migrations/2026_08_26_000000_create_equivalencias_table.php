<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencias para el ranking de productos.
 *
 * Muchos renglones no nombran el producto: "12 paquetes de 8 a 15 años" no dice
 * "Calzoncito" en ninguna parte. Acá se enseña una vez qué significa cada forma
 * de escribirlo, y el ranking lo entiende de ahí en adelante (y hacia atrás,
 * porque se recalcula sobre lo ya guardado).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equivalencias')) return;

        Schema::create('equivalencias', function (Blueprint $table) {
            $table->id();

            // Trozo de texto que hay que reconocer: "8 a 15", "RN", "magic".
            $table->string('texto', 120)->index();

            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();

            // Talla a la que corresponde, si el texto la implica.
            $table->string('talla', 60)->nullable();

            $table->string('nota', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equivalencias');
    }
};
