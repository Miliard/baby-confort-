<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        // Un pedido se registra apenas se arma la guía, antes de que Sistrack
        // devuelva el número. Así el cliente ya puede rastrear con su teléfono
        // y ve "pedido confirmado"; el número de guía se rellena al importar el PDF.
        try {
            DB::statement('ALTER TABLE guia_fotos MODIFY guia VARCHAR(255) NULL');
        } catch (\Throwable $e) {
            // Si el motor no lo soporta, se deja como está (no rompe nada).
        }
    }

    public function down(): void
    {
        // Se deja igual: volver a NOT NULL podría fallar si hay pedidos sin guía.
    }
};
