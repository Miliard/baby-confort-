<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cada guía en el sistema nuevo de Express (Facilito / T&T).
 *
 * Lo llena el botón "📦 Actualizar guías" que se toca con Express abierto en el
 * navegador. El rastreo del cliente y la revisión de entregas leen de aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guia_estados')) return;

        Schema::create('guia_estados', function (Blueprint $table) {
            $table->id();
            $table->string('guia', 40)->unique();
            $table->string('estado', 40)->nullable();          // código de Express: IN_TRANSIT, DELIVERED…
            $table->unsignedTinyInteger('etapa')->default(1);   // 1-4, la barra del rastreo
            $table->string('repartidor', 120)->nullable();
            $table->string('pago', 40)->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('cambio_at')->nullable();         // última vez que cambió en Express
            $table->json('historial')->nullable();              // cambios vistos, del más nuevo al más viejo
            $table->unsignedTinyInteger('faltas')->default(0);  // veces que Express no la encontró
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_estados');
    }
};
