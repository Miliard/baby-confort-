<?php

namespace App\Http\Controllers;

use App\Models\WaConversacion;
use App\Models\WaMensaje;
use Illuminate\Http\Request;

/**
 * Descarga TODAS las conversaciones de un período en un solo archivo de
 * texto, para analizarlas y mejorar el asistente.
 *
 * Sin datos personales: cada cliente pasa a ser "Cliente 1", "Cliente 2"…, y
 * se tapan teléfonos, correos, nombres y direcciones de las órdenes. Lo que
 * queda es cómo escriben las clientas y cómo se les contesta, que es lo que
 * sirve para mejorar.
 */
class ChatsExportController extends Controller
{
    public function descargar(Request $request)
    {
        @set_time_limit(300);

        $dias  = (int) $request->query('dias', 60);
        $desde = $dias > 0 ? now()->subDays(min($dias, 730)) : null;
        $zona  = 'America/El_Salvador';

        $nombre = 'chats-baby-confort-' . now($zona)->format('Y-m-d') . ($dias > 0 ? "-ultimos-{$dias}-dias" : '-todos') . '.txt';

        return response()->streamDownload(function () use ($desde, $dias, $zona) {
            $hayFichas = \App\Models\AsistenteFicha::hayTabla();

            $q = WaConversacion::query()
                ->with('etiquetas')
                ->orderBy('ultimo_mensaje_at');

            if ($desde) $q->where('ultimo_mensaje_at', '>=', $desde);

            $total = (clone $q)->count();

            echo "CHATS DE BABY-CONFORT PARA ANALIZAR\n";
            echo 'Período: ' . ($dias > 0 ? "últimos {$dias} días" : 'todo el historial')
                . ' · descargado el ' . now($zona)->format('d/m/Y H:i') . "\n";
            echo "Conversaciones: {$total}\n";
            echo "Sin datos personales: teléfonos, nombres y direcciones van tapados.\n";
            echo "Quién habla: CLIENTE · TIENDA (vos o el equipo) · ASISTENTE (respuesta automática)\n\n";

            // Resumen de por qué el asistente pasó chats a Wil: es lo primero
            // que hay que mirar para mejorarlo.
            if ($hayFichas) {
                try {
                    $motivos = \App\Models\AsistenteFicha::query()
                        ->whereNotNull('motivo')
                        ->when($desde, fn ($w) => $w->where('updated_at', '>=', $desde))
                        ->selectRaw('motivo, count(*) as n')
                        ->groupBy('motivo')
                        ->orderByDesc('n')
                        ->get();

                    if ($motivos->isNotEmpty()) {
                        echo "POR QUÉ EL ASISTENTE PASÓ CHATS A LA TIENDA\n";
                        foreach ($motivos as $m) echo "  {$m->n} × {$m->motivo}\n";
                        echo "\n";
                    }
                } catch (\Throwable $e) {
                }
            }

            $n = 0;

            $q->chunk(100, function ($convs) use (&$n, $desde, $zona, $hayFichas) {
                foreach ($convs as $conv) {
                    $n++;

                    $msjs = WaMensaje::where('conversacion_id', $conv->id)
                        ->when($desde, fn ($w) => $w->where('created_at', '>=', $desde))
                        ->orderBy('id')
                        ->get(['id', 'direccion', 'tipo', 'texto', 'automatico', 'user_id', 'created_at']);

                    if ($msjs->isEmpty()) continue;

                    $tapar = $this->palabrasATapar($conv);

                    echo str_repeat('═', 60) . "\n";
                    echo "Cliente {$n} · {$msjs->count()} mensajes · "
                        . $msjs->first()->created_at->timezone($zona)->format('d/m/Y')
                        . ' → ' . $msjs->last()->created_at->timezone($zona)->format('d/m/Y')
                        . ($conv->esExtranjero() ? ' · escribe desde el extranjero' : '') . "\n";

                    $etqs = $conv->etiquetas->pluck('nombre')->filter()->implode(', ');
                    if ($etqs !== '') echo "Etiquetas: {$etqs}\n";

                    if ($hayFichas) {
                        $f = \App\Models\AsistenteFicha::where('conversacion_id', $conv->id)->first();
                        if ($f) echo 'Asistente: ' . $f->estadoLegible() . ($f->motivo ? " — {$f->motivo}" : '') . "\n";
                    }

                    echo "\n";

                    foreach ($msjs as $m) {
                        $quien = $m->direccion === 'entrante' ? 'CLIENTE' : ($m->automatico ? 'ASISTENTE' : 'TIENDA');

                        $tipo = match ($m->tipo) {
                            'image' => ' (foto)', 'audio', 'voice' => ' (audio)', 'video' => ' (video)',
                            'document' => ' (documento)', 'sticker' => ' (sticker)', default => '',
                        };

                        $texto = $this->limpiar((string) $m->texto, $tapar);
                        if ($texto === '' && $tipo === '') continue;

                        echo '[' . $m->created_at->timezone($zona)->format('d/m H:i') . "] {$quien}{$tipo}: "
                            . str_replace("\n", "\n    ", $texto) . "\n";
                    }

                    echo "\n";
                }

                if (function_exists('flush')) @flush();
            });
        }, $nombre, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** El nombre y el apodo del cliente, para taparlos donde aparezcan. */
    private function palabrasATapar(WaConversacion $conv): array
    {
        $p = [];

        foreach ([$conv->nombre, $conv->alias] as $n) {
            $n = trim((string) $n);
            if (mb_strlen($n) >= 3 && preg_match('/\p{L}/u', $n)) $p[] = $n;
        }

        return $p;
    }

    private function limpiar(string $t, array $tapar): string
    {
        $t = trim($t);
        if ($t === '') return '';

        // Datos de las órdenes de envío: la línea entera.
        $t = preg_replace('/^(\W*\s*(nombre completo|nombre|direcci[oó]n exacta|direcci[oó]n|tel[eé]fono|celular))\s*:.*$/imu', '$1: [tapado]', $t);

        // Teléfonos (de acá y de afuera) y correos.
        $t = preg_replace('/\+?\d[\d\s().-]{7,}\d/u', '[teléfono]', $t);
        $t = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/u', '[correo]', $t);

        foreach ($tapar as $n) {
            $t = str_ireplace($n, '[nombre]', $t);
        }

        return $t;
    }
}
