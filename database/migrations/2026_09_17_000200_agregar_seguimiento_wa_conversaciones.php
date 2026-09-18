<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que hace falta para vigilar la entrega de cada conversación.
 *
 * El estado del paquete vive en la página del courier, no acá. De esa página
 * solo se guarda lo mínimo para poder decidir: qué guía se está mirando, en qué
 * etapa iba, cuántas veces seguidas dijo "entregado" y cuándo se revisó.
 *
 * Lo de las veces seguidas no es un capricho: al repartidor se le ha ido marcar
 * entregado por error y corregirlo después. Si se creyera a la primera, la
 * conversación se movería a Entregados y —al ser el final del recorrido— nadie
 * volvería a preguntar, así que el error se quedaría ahí para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_conversaciones')) return;

        Schema::table('wa_conversaciones', function (Blueprint $t) {
            if (! Schema::hasColumn('wa_conversaciones', 'guia')) {
                $t->string('guia', 40)->nullable()->after('fijada_at');
            }

            if (! Schema::hasColumn('wa_conversaciones', 'etapa_envio')) {
                // 1 confirmado · 2 recolectado · 3 en camino · 4 entregado
                $t->unsignedTinyInteger('etapa_envio')->nullable()->after('guia');
            }

            if (! Schema::hasColumn('wa_conversaciones', 'entregas_seguidas')) {
                $t->unsignedTinyInteger('entregas_seguidas')->default(0)->after('etapa_envio');
            }

            if (! Schema::hasColumn('wa_conversaciones', 'revisado_at')) {
                $t->timestamp('revisado_at')->nullable()->after('entregas_seguidas');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_conversaciones')) return;

        Schema::table('wa_conversaciones', function (Blueprint $t) {
            foreach (['guia', 'etapa_envio', 'entregas_seguidas', 'revisado_at'] as $c) {
                if (Schema::hasColumn('wa_conversaciones', $c)) $t->dropColumn($c);
            }
        });
    }
};
