<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las fotos de cómo queda puesto el producto, por presentación.
 *
 * Van en la talla y no en el producto porque es ahí donde importan: cómo queda
 * un Magic M no se parece a cómo queda un XXL, y mandar la foto equivocada
 * confunde más que no mandar ninguna.
 *
 * NO se muestran en la página. Existen solo para el chat: son fotos reales,
 * tomadas para convencer a alguien que está preguntando por WhatsApp, no
 * material de catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_sizes')) return;
        if (Schema::hasColumn('product_sizes', 'fotos_uso')) return;

        Schema::table('product_sizes', function (Blueprint $t) {
            // Un JSON con las rutas. Varias por talla, en el orden en que se
            // suban, que es el orden en que se mandan.
            $t->json('fotos_uso')->nullable()->after('image_upload');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_sizes')) return;
        if (! Schema::hasColumn('product_sizes', 'fotos_uso')) return;

        Schema::table('product_sizes', function (Blueprint $t) {
            $t->dropColumn('fotos_uso');
        });
    }
};
