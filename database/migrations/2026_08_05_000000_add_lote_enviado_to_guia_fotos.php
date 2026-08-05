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
            // Identifica el lote de subida (todas las fotos que se subieron juntas).
            if (! Schema::hasColumn('guia_fotos', 'lote')) {
                $table->string('lote', 40)->nullable()->index()->after('telefono');
            }
            // Marca cuándo se copió/envió el enlace al cliente.
            if (! Schema::hasColumn('guia_fotos', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable()->after('lote');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $table) {
            if (Schema::hasColumn('guia_fotos', 'lote')) $table->dropColumn('lote');
            if (Schema::hasColumn('guia_fotos', 'enviado_at')) $table->dropColumn('enviado_at');
        });
    }
};
