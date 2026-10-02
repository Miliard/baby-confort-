<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ya la mandé yo a mano."
 *
 * Cuando la ventana de 24 horas está cerrada, la foto no puede salir por el
 * panel y Wil la manda desde el teléfono. Esta marca dice eso, para que la
 * foto quede como enviada y no vuelva a la lista de pendientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos') || Schema::hasColumn('guia_fotos', 'chat_manual_at')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            $t->timestamp('chat_manual_at')->nullable()->after('chat_enviada_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('guia_fotos', 'chat_manual_at')) {
            Schema::table('guia_fotos', fn (Blueprint $t) => $t->dropColumn('chat_manual_at'));
        }
    }
};
