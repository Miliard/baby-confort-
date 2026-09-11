<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca a los colaboradores que SOLO deben entrar al panel de chat.
 *
 * Sirve para que quien contesta WhatsApp no vea el Cierre del día, las
 * remuneraciones ni los números del negocio. Por defecto va en false: los
 * usuarios que ya existen siguen entrando al admin completo, sin cambios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) return;

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'solo_chat')) {
                $table->boolean('solo_chat')->default(false)->after('email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) return;

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'solo_chat')) {
                $table->dropColumn('solo_chat');
            }
        });
    }
};
