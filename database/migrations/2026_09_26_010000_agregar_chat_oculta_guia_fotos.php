<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Enviada" y "limpiada" pasan a ser dos cosas distintas.
 *
 * Hasta acá eran una sola marca (chat_enviada_at), y de ahí salían todos los
 * problemas: una foto mandada desaparecía de su tanda al instante, igual que
 * una que se quitaba con el tacho. No había forma de mirar una tanda y ver
 * cuáles ya salieron y cuáles no — que es lo único que hace falta ver.
 *
 * Ahora:
 *   chat_enviada_at → la foto le salió al cliente. Se queda en su tanda con
 *                     la marca de enviada.
 *   chat_oculta_at  → la tanda se limpió con el tacho. Recién ahí se va.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (! Schema::hasColumn('guia_fotos', 'chat_oculta_at')) {
                $t->timestamp('chat_oculta_at')->nullable()->after('chat_enviada_at');
            }
        });

        // Arranque limpio: todo lo que ya estaba mandado o quitado queda
        // oculto. Si no, al subir esto reaparecerían de golpe las tandas de
        // toda la semana, ya mandadas, tapando la de hoy.
        try {
            DB::table('guia_fotos')
                ->whereNotNull('chat_enviada_at')
                ->whereNull('chat_oculta_at')
                ->update(['chat_oculta_at' => DB::raw('chat_enviada_at')]);
        } catch (\Throwable $e) {
            // Si falla, lo peor que pasa es ver tandas viejas: se limpian a mano.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (Schema::hasColumn('guia_fotos', 'chat_oculta_at')) {
                $t->dropColumn('chat_oculta_at');
            }
        });
    }
};
