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
            // La última columna de la liquidación trae explicaciones de Express:
            // "DEPOSITO A TIENDA $27,50", "CAMBIO DE PRECIO...", "TYP", "1 DE 2".
            if (! Schema::hasColumn('express_entregas', 'nota')) {
                $table->string('nota', 300)->nullable()->after('total');
            }
            // Los renglones marcados TYP son repeticiones del mismo bulto.
            if (! Schema::hasColumn('express_entregas', 'duplicado')) {
                $table->boolean('duplicado')->default(false)->after('nota');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('express_entregas')) return;

        Schema::table('express_entregas', function (Blueprint $table) {
            foreach (['nota', 'duplicado'] as $c) {
                if (Schema::hasColumn('express_entregas', $c)) $table->dropColumn($c);
            }
        });
    }
};
