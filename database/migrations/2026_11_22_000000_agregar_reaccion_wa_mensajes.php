<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La reacción del cliente (👍 ❤️ 🙏…) sobre un mensaje, como en WhatsApp:
 * se guarda pegada al mensaje al que reaccionó, no como un mensaje aparte
 * que decía "[reaction]" y no se entendía.
 *
 * Al final y sin índice, igual que fijado_at: en MySQL 8 se agrega al
 * instante sin trabar la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_mensajes')) return;
        if (Schema::hasColumn('wa_mensajes', 'reaccion')) return;

        Schema::table('wa_mensajes', function (Blueprint $t) {
            $t->string('reaccion', 32)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_mensajes')) return;
        if (! Schema::hasColumn('wa_mensajes', 'reaccion')) return;

        Schema::table('wa_mensajes', function (Blueprint $t) {
            $t->dropColumn('reaccion');
        });
    }
};
