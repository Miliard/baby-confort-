<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guias_borrador')) return;

        Schema::table('guias_borrador', function (Blueprint $table) {
            // Marca cuándo se le mandó el enlace de rastreo al cliente,
            // para saber de un vistazo a quién falta.
            if (! Schema::hasColumn('guias_borrador', 'enviado_at')) {
                $table->timestamp('enviado_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guias_borrador')) return;

        Schema::table('guias_borrador', function (Blueprint $table) {
            if (Schema::hasColumn('guias_borrador', 'enviado_at')) {
                $table->dropColumn('enviado_at');
            }
        });
    }
};
