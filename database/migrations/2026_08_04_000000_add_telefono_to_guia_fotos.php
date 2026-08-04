<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guia_fotos') && ! Schema::hasColumn('guia_fotos', 'telefono')) {
            Schema::table('guia_fotos', function (Blueprint $table) {
                $table->string('telefono', 30)->nullable()->after('nombre');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('guia_fotos') && Schema::hasColumn('guia_fotos', 'telefono')) {
            Schema::table('guia_fotos', function (Blueprint $table) {
                $table->dropColumn('telefono');
            });
        }
    }
};
