<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas para clasificar conversaciones, como las de WhatsApp Business.
 *
 * No son las del teléfono: Meta no las expone a la API, así que no hay manera
 * de leerlas ni de sincronizarlas. Estas son propias del panel, y a cambio las
 * ven los tres colaboradores, no solo el dueño del teléfono.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_etiquetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');

            // Un color de la lista corta, para que se distingan de un vistazo.
            $table->string('color', 20)->default('verde');

            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Una conversación puede llevar varias etiquetas a la vez: "pedidos" y
        // "san miguel" al mismo tiempo es lo normal.
        Schema::create('wa_conversacion_etiqueta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversacion_id')
                ->constrained('wa_conversaciones')
                ->cascadeOnDelete();

            $table->foreignId('etiqueta_id')
                ->constrained('wa_etiquetas')
                ->cascadeOnDelete();

            $table->unique(['conversacion_id', 'etiqueta_id']);
        });

        // Las que Wil ya usa en el teléfono, para no cargarlas a mano.
        DB::table('wa_etiquetas')->insert([
            ['nombre' => 'Pedidos',    'color' => 'azul',     'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Preparados', 'color' => 'amarillo', 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Entregados', 'color' => 'verde',    'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'San Miguel', 'color' => 'morado',   'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_conversacion_etiqueta');
        Schema::dropIfExists('wa_etiquetas');
    }
};
