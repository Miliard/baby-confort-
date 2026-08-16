<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('express_entregas')) return;

        Schema::table('express_entregas', function (Blueprint $table) {
            // El paquete se entrega un día y la plata entra otro. Son dos relojes
            // distintos: el resultado se mide por entrega, la caja por depósito.
            if (! Schema::hasColumn('express_entregas', 'fecha_deposito')) {
                $table->date('fecha_deposito')->nullable()->index()->after('fecha');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('express_entregas')) return;

        Schema::table('express_entregas', function (Blueprint $table) {
            if (Schema::hasColumn('express_entregas', 'fecha_deposito')) {
                $table->dropColumn('fecha_deposito');
            }
        });
    }
};
