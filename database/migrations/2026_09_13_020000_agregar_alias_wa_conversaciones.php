<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El nombre que le pone Wil a cada contacto.
 *
 * El campo "nombre" que ya existía trae lo que el cliente puso en su propio
 * perfil de WhatsApp, y suele ser un apodo o un emoji: "Belleza❤️", "PHP".
 * Para reconocer a alguien y ubicarlo sirve más "Marta San Miguel", que es
 * como lo anota uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('wa_conversaciones', 'alias')) return;

        Schema::table('wa_conversaciones', function (Blueprint $table) {
            $table->string('alias')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('wa_conversaciones', 'alias')) return;

        Schema::table('wa_conversaciones', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
