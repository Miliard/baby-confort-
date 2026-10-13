<?php

use App\Models\WaEtiqueta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * La pestaña "🤖 Revisar": los chats que el asistente dejó para una persona
 * (dos tallas, audio, foto, no entendió…). Se pone sola cuando el asistente
 * pasa el chat, y se quita sola cuando alguien del equipo contesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_etiquetas') || ! Schema::hasColumn('wa_etiquetas', 'rol')) return;

        try {
            if (! WaEtiqueta::where('rol', 'asistente')->exists()) {
                WaEtiqueta::create([
                    'nombre' => '🤖 Revisar',
                    'rol'    => 'asistente',
                    'color'  => 'rojo',
                    'orden'  => 0,   // la primera de los filtros: es trabajo pendiente
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Creando la etiqueta Revisar: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
    }
};
