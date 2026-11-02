<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes fijados dentro de un chat (la dirección, lo que pidió, un acuerdo):
 * quedan en una barra arriba de la conversación, en el panel.
 *
 * Sin "after" ni índice a propósito: wa_mensajes es la tabla más grande y se
 * escribe todo el tiempo. Una columna nueva al final se agrega al instante en
 * MySQL 8 sin trabar la tabla. Se busca siempre dentro de un solo chat, que
 * ya va por conversacion_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_mensajes')) return;
        if (Schema::hasColumn('wa_mensajes', 'fijado_at')) return;

        Schema::table('wa_mensajes', function (Blueprint $t) {
            $t->timestamp('fijado_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_mensajes')) return;
        if (! Schema::hasColumn('wa_mensajes', 'fijado_at')) return;

        Schema::table('wa_mensajes', function (Blueprint $t) {
            $t->dropColumn('fijado_at');
        });
    }
};
