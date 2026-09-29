<?php

use App\Models\WaEtiqueta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * La pestaña "Agotados", y los chats de antes que van en ella.
 *
 * Dos cosas, una sola vez al subir el cambio:
 *
 *  1. Crea la etiqueta "Agotados" con su papel, para que la regla automática
 *     tenga dónde marcar. Si ya existía una con ese nombre (hecha a mano), se
 *     le asigna el papel a esa en vez de crear otra repetida.
 *
 *  2. Recorre TODOS los mensajes guardados y marca las conversaciones donde
 *     alguna vez le dijiste a un cliente que algo se agotó. Así la lista no
 *     arranca vacía: están todos los que preguntaron antes de que existiera
 *     la regla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_etiquetas') || ! Schema::hasColumn('wa_etiquetas', 'rol')) return;

        try {
            $etq = WaEtiqueta::where('rol', 'agotado')->first()
                ?? WaEtiqueta::where('nombre', 'like', 'agotad%')->first();

            if ($etq) {
                $etq->rol = 'agotado';
                $etq->save();
            } else {
                WaEtiqueta::create([
                    'nombre' => 'Agotados',
                    'rol'    => 'agotado',
                    'color'  => 'gris',
                    // Al final de los filtros: es una lista de consulta, no de
                    // trabajo del día como Pedidos o Preparados.
                    'orden'  => 90,
                ]);
            }

            $n = \App\Services\Etiquetado::marcarAgotadosDelHistorial();

            Log::info("Agotados: {$n} conversaciones marcadas desde el historial.");
        } catch (\Throwable $e) {
            // Si algo falla acá, el deploy tiene que seguir igual: la regla
            // de ahora en adelante funciona aunque el historial no se haya
            // recorrido.
            Log::warning('Creando Agotados: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // No se borra: la etiqueta puede tener conversaciones que depuraste a
        // mano, y perder eso sería peor que dejarla.
    }
};
