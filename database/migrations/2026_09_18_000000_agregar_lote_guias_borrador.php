<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los lotes de guías.
 *
 * Las guías se bajan de a montones, no una por una, y la lista no se vacía al
 * bajarlas —a propósito, por si hay que repetir el archivo—. El problema es lo
 * que pasa después: si un colaborador agrega una guía y alguien vuelve a bajar
 * el Excel, salen otra vez todas las anteriores y Sistrack las recibe repetidas.
 *
 * Con esto, bajar el Excel cierra el lote: las que iban quedan marcadas con su
 * número y con la fecha, y las que entren después arrancan el siguiente. La
 * próxima bajada trae solo esas.
 *
 * Sin número = todavía no se ha bajado = va en el lote que viene.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guias_borrador')) return;

        Schema::table('guias_borrador', function (Blueprint $t) {
            if (! Schema::hasColumn('guias_borrador', 'lote')) {
                $t->unsignedInteger('lote')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('guias_borrador', 'descargado_at')) {
                $t->timestamp('descargado_at')->nullable()->after('lote');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guias_borrador')) return;

        Schema::table('guias_borrador', function (Blueprint $t) {
            foreach (['lote', 'descargado_at'] as $c) {
                if (Schema::hasColumn('guias_borrador', $c)) $t->dropColumn($c);
            }
        });
    }
};
