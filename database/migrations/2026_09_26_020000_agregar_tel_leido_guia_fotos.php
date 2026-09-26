<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El teléfono tal como lo leyó la etiqueta, antes de corregirlo.
 *
 * El número que se usa para mandar la foto ahora puede venir de emparejarla
 * con las conversaciones de Preparados, aunque la etiqueta se haya leído con
 * un dígito mal. Guardar lo que se leyó es lo que permite mostrarte "la
 * etiqueta decía 6996 1244, se tomó 6996 1224" — y que vos veas de un vistazo
 * si la corrección tiene sentido, en vez de tener que creerle al sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (! Schema::hasColumn('guia_fotos', 'tel_leido')) {
                $t->string('tel_leido', 30)->nullable()->after('telefono');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (Schema::hasColumn('guia_fotos', 'tel_leido')) {
                $t->dropColumn('tel_leido');
            }
        });
    }
};
