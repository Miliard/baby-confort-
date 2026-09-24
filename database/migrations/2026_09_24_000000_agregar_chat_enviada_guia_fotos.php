<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de que la foto del paquete ya se le mandó al cliente por el chat.
 *
 * Va aparte de "enviado_at", que ya existía y quiere decir otra cosa: que se
 * le mandó el ENLACE de rastreo. Son dos cosas distintas y pueden pasar por
 * separado — le podés haber mandado el enlace ayer y la foto hoy.
 *
 * Juntarlas en una sola columna habría hecho que mandar una diera la otra por
 * mandada, y ahí se pierde justo lo que esto viene a resolver: saber qué falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (! Schema::hasColumn('guia_fotos', 'chat_enviada_at')) {
                $t->timestamp('chat_enviada_at')->nullable()->after('enviado_at');
            }

            // Por qué no salió, cuando no sale. Sin esto, una foto que falla
            // se ve igual que una que nadie tocó todavía, y no hay manera de
            // saber si hay que esperar o si hay que hacer algo.
            if (! Schema::hasColumn('guia_fotos', 'chat_error')) {
                $t->string('chat_error', 190)->nullable()->after('chat_enviada_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            foreach (['chat_enviada_at', 'chat_error'] as $c) {
                if (Schema::hasColumn('guia_fotos', $c)) $t->dropColumn($c);
            }
        });
    }
};
