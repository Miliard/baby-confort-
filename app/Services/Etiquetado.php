<?php

namespace App\Services;

use App\Models\WaConversacion;
use App\Models\WaEtiqueta;
use Illuminate\Support\Facades\Log;

/**
 * Mueve las conversaciones de etiqueta solas, según lo que pasa en el chat.
 *
 * El problema que resuelve es de personas, no de programa: la orden de envío se
 * escribe, se manda, y a nadie se le ocurre que además hay que ir a ponerle la
 * etiqueta. Así el pedido queda sin marcar y después nadie lo encuentra.
 *
 * Entonces el panel lo hace por su cuenta:
 *
 *   · Aparece una orden de envío en el chat  → etiqueta "pedido"
 *   · Sale el enlace de rastreo              → etiqueta "procesada", y se
 *                                              quita la de "pedido"
 *
 * Da igual de dónde venga el mensaje: escrito desde el panel, desde la app del
 * teléfono, o mandado por el propio cliente. Todos los mensajes pasan por
 * WaMensaje al guardarse, y de ahí se dispara esto.
 *
 * Qué etiqueta juega cada papel lo elige Wil en el admin. Si no eligió
 * ninguna, no pasa nada: no se etiqueta y el panel sigue igual que antes.
 */
class Etiquetado
{
    /** ¿Este texto es una orden de envío? */
    public static function pareceOrden(?string $texto): bool
    {
        $t = trim((string) $texto);
        if ($t === '') return false;

        // La plantilla de Wil lleva el encabezado; "Total a pagar" queda como
        // respaldo por si alguien la escribió a mano sin el título.
        foreach (['Orden de Envío', 'Orden de Envio', 'Total a pagar'] as $pista) {
            if (mb_stripos($t, $pista) !== false) return true;
        }

        return false;
    }

    /** ¿Este texto lleva el enlace de rastreo? */
    public static function llevaRastreo(?string $texto): bool
    {
        $t = trim((string) $texto);
        if ($t === '') return false;

        return mb_stripos($t, '/rastreo') !== false;
    }

    /**
     * Se llama con cada mensaje nuevo, venga de donde venga.
     *
     * El orden importa: primero se pregunta por el rastreo. Un mensaje que
     * lleva el enlace es el final del recorrido, aunque de casualidad también
     * mencionara un total.
     */
    public static function alGuardarMensaje(?WaConversacion $conv, ?string $texto): void
    {
        if (! $conv) return;

        try {
            if (static::llevaRastreo($texto)) {
                static::marcarProcesada($conv);
                return;
            }

            if (static::pareceOrden($texto)) {
                static::marcarPedido($conv);
            }
        } catch (\Throwable $e) {
            // Etiquetar es una comodidad. Que falle no puede costar un mensaje
            // ni tumbar el webhook.
            Log::warning('Etiquetado automático: ' . $e->getMessage());
        }
    }

    /** Hay una orden en esta conversación. */
    public static function marcarPedido(WaConversacion $conv): void
    {
        $pedido = WaEtiqueta::porRol('pedido');
        if (! $pedido) return;

        // syncWithoutDetaching: no le toca las demás etiquetas. Si además está
        // marcada como "San Miguel", sigue estándolo.
        $conv->etiquetas()->syncWithoutDetaching([$pedido->id]);
    }

    /**
     * Esta conversación ya tiene su guía y su enlace mandado.
     *
     * Acá sí se quita la de "pedido": lo que se está diciendo es que pasó de
     * una etapa a la otra, y dejar las dos puestas haría que apareciera en los
     * dos filtros a la vez.
     */
    public static function marcarProcesada(WaConversacion $conv): void
    {
        $lista = WaEtiqueta::porRol('procesada');
        if ($lista) $conv->etiquetas()->syncWithoutDetaching([$lista->id]);

        $pedido = WaEtiqueta::porRol('pedido');
        if ($pedido) $conv->etiquetas()->detach($pedido->id);
    }
}
