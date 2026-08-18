<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $table) {
            // Cuándo se borró la imagen del disco. El registro se queda para
            // siempre (pesa nada): así el rastreo y el ranking de productos
            // siguen funcionando aunque la foto ya no esté.
            if (! Schema::hasColumn('guia_fotos', 'foto_borrada_at')) {
                $table->timestamp('foto_borrada_at')->nullable()->after('ruta');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $table) {
            if (Schema::hasColumn('guia_fotos', 'foto_borrada_at')) {
                $table->dropColumn('foto_borrada_at');
            }
        });
    }
};
