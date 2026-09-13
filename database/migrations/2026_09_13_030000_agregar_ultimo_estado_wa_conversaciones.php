<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De quién fue el último mensaje y cómo le fue.
 *
 * Sirve para lo más importante de la lista: saber a quién le debés respuesta.
 * Si el último mensaje es del cliente, ese chat está esperando. Si es tuyo,
 * ya contestaste y las palomitas dicen si lo leyó.
 *
 * Se guarda en la conversación y no se consulta el último mensaje cada vez,
 * porque la lista muestra sesenta conversaciones y serían sesenta consultas
 * más en cada refresco, tres veces por minuto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_conversaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_conversaciones', 'ultimo_saliente')) {
                $table->boolean('ultimo_saliente')->default(false);
            }

            if (! Schema::hasColumn('wa_conversaciones', 'ultimo_estado')) {
                $table->string('ultimo_estado', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversaciones', function (Blueprint $table) {
            $table->dropColumn(['ultimo_saliente', 'ultimo_estado']);
        });
    }
};
