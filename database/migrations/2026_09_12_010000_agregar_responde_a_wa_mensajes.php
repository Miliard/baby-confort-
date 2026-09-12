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
        Schema::table('wa_mensajes', function (Blueprint $table) {
            $table->string('responde_a')->nullable()->after('wa_message_id');
            $table->index('responde_a');
        });
    }

    public function down(): void
    {
        Schema::table('wa_mensajes', function (Blueprint $table) {
            $table->dropIndex(['responde_a']);
            $table->dropColumn('responde_a');
        });
    }
};
