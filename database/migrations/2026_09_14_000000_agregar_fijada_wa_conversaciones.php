<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversaciones fijadas arriba de la lista, como en WhatsApp.
 *
 * Se guarda la fecha y no un sí/no: así, si hay varias fijadas, se pueden
 * ordenar entre ellas por cuál se fijó de último, y queda el dato de cuándo
 * se hizo por si alguna vez sirve.
 *
 * Sin índice a propósito: wa_conversaciones se escribe todo el tiempo y crear
 * un índice sobre ella traba la tabla mientras se despliega. La lista trae 60
 * filas como mucho; no lo necesita.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_conversaciones')) return;
        if (Schema::hasColumn('wa_conversaciones', 'fijada_at')) return;

        Schema::table('wa_conversaciones', function (Blueprint $t) {
            $t->timestamp('fijada_at')->nullable()->after('archivada');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_conversaciones')) return;
        if (! Schema::hasColumn('wa_conversaciones', 'fijada_at')) return;

        Schema::table('wa_conversaciones', function (Blueprint $t) {
            $t->dropColumn('fijada_at');
        });
    }
};
