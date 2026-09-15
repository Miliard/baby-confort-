<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El papel que juega cada etiqueta en el recorrido de un pedido.
 *
 * Las etiquetas se llaman como quiera Wil —"Pedidos", "Preparados"— y puede
 * renombrarlas cuando se le antoje. Lo que el panel necesita saber no es el
 * nombre sino la función: cuál se pone cuando llega una orden y cuál cuando ya
 * se mandó el enlace de rastreo. Por eso el papel va aparte del nombre.
 *
 * Con esto el etiquetado deja de depender de que alguien se acuerde.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_etiquetas')) return;
        if (Schema::hasColumn('wa_etiquetas', 'rol')) return;

        Schema::table('wa_etiquetas', function (Blueprint $t) {
            $t->string('rol', 20)->nullable()->after('nombre');
        });

        // Las dos que ya existen con ese sentido quedan conectadas solas. Si
        // las renombró, no pasa nada: se eligen a mano en el admin.
        try {
            DB::table('wa_etiquetas')->where('nombre', 'Pedidos')->update(['rol' => 'pedido']);
            DB::table('wa_etiquetas')->where('nombre', 'Preparados')->update(['rol' => 'procesada']);
        } catch (\Throwable $e) {
            // Que no se pueda adivinar no justifica que falle el despliegue.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_etiquetas')) return;
        if (! Schema::hasColumn('wa_etiquetas', 'rol')) return;

        Schema::table('wa_etiquetas', function (Blueprint $t) {
            $t->dropColumn('rol');
        });
    }
};
