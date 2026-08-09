<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        // La guía puede registrarse desde el PDF (sin foto todavía); la foto llega después.
        try {
            DB::statement('ALTER TABLE guia_fotos MODIFY ruta VARCHAR(255) NULL');
        } catch (\Throwable $e) {
            // Si el motor no lo soporta, se deja como está (no rompe nada).
        }
    }

    public function down(): void
    {
        // Se deja igual: volver a NOT NULL podría fallar si hay guías sin foto.
    }
};
