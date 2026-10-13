<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Encendido a mano" en un chat: el asistente atiende ESE chat aunque el botón
 * general esté apagado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asistente_fichas') || Schema::hasColumn('asistente_fichas', 'a_mano')) return;

        Schema::table('asistente_fichas', function (Blueprint $table) {
            $table->boolean('a_mano')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('asistente_fichas', 'a_mano')) {
            Schema::table('asistente_fichas', fn (Blueprint $t) => $t->dropColumn('a_mano'));
        }
    }
};
