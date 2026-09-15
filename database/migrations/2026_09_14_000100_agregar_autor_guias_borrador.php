<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién armó cada guía.
 *
 * Nació de un problema real: con varias personas metiendo guías en la misma
 * cola, uno mira la lista y no sabe si esa la hizo él o un colaborador. La
 * hora ya estaba (created_at); faltaba el nombre.
 *
 * Sin llave foránea a propósito: si algún día se borra un usuario, la guía
 * tiene que seguir existiendo. Se queda sin nombre y ya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guias_borrador')) return;
        if (Schema::hasColumn('guias_borrador', 'user_id')) return;

        Schema::table('guias_borrador', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->after('cobrar');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guias_borrador')) return;
        if (! Schema::hasColumn('guias_borrador', 'user_id')) return;

        Schema::table('guias_borrador', function (Blueprint $t) {
            $t->dropColumn('user_id');
        });
    }
};
