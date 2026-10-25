<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guías enviadas por la API de Express: el número que devolvió Express y el
 * último error, si lo hubo. Con "guia" lleno, esa guía ya no se vuelve a mandar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guias_borrador') || Schema::hasColumn('guias_borrador', 'guia')) return;

        Schema::table('guias_borrador', function (Blueprint $table) {
            $table->string('guia', 40)->nullable()->after('cobrar');
            $table->text('express_error')->nullable()->after('guia');
            $table->timestamp('express_at')->nullable()->after('express_error');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('guias_borrador', 'guia')) {
            Schema::table('guias_borrador', fn (Blueprint $t) => $t->dropColumn(['guia', 'express_error', 'express_at']));
        }
    }
};
