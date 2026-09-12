<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La columna "value" era VARCHAR(255) y alcanzaba de sobra para lo que había
 * (costo de envío, mínimo para envío gratis).
 *
 * Ahora también guarda el identificador de acceso de WhatsApp, que ronda los
 * 250 caracteres y a veces los pasa. En MySQL eso se corta o revienta según la
 * configuración, y el resultado sería un token roto que falla sin decir por
 * qué. Pasarla a TEXT quita el problema de raíz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('value')->nullable()->change();
        });
    }
};
