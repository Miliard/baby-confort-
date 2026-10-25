<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Link público de rastreo que da la API de Express para cada guía. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_estados') || Schema::hasColumn('guia_estados', 'url_publica')) return;

        Schema::table('guia_estados', function (Blueprint $table) {
            $table->string('url_publica', 255)->nullable()->after('pago');
            $table->unsignedBigInteger('express_id')->nullable()->after('guia');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('guia_estados', 'url_publica')) {
            Schema::table('guia_estados', fn (Blueprint $t) => $t->dropColumn(['url_publica', 'express_id']));
        }
    }
};
