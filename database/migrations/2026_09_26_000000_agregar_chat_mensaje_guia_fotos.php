<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué mensaje de WhatsApp salió con cada foto.
 *
 * Hace falta porque WhatsApp contesta en DOS tiempos. Primero dice "recibido"
 * y el panel lo daba por mandado. Después —segundos o minutos más tarde— va a
 * buscar la imagen a nuestro sitio, y recién ahí puede fallar: formato que no
 * acepta, la foto no se deja descargar, el cliente bloqueó el número.
 *
 * Esa segunda respuesta llega sola, por el webhook, sin decir de qué foto se
 * trata: solo dice qué MENSAJE falló. Sin guardar el mensaje junto a la foto,
 * no había forma de volver de "este mensaje falló" a "esta foto no llegó". La
 * foto quedaba marcada como mandada, y nunca le llegó a nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (! Schema::hasColumn('guia_fotos', 'chat_mensaje_id')) {
                $t->unsignedBigInteger('chat_mensaje_id')->nullable()->after('chat_error');
                $t->index('chat_mensaje_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (Schema::hasColumn('guia_fotos', 'chat_mensaje_id')) {
                $t->dropIndex(['chat_mensaje_id']);
                $t->dropColumn('chat_mensaje_id');
            }
        });
    }
};
