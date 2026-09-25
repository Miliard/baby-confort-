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
     * ACÁ SOLO SE MARCA "pedido". El paso a "procesada" ya no lo dispara el
     * enlace de rastreo: lo dispara mandarle al cliente la FOTO de su
     * etiqueta (ver FotosAlChat::mandarUna).
     *
     * El enlace no servía como señal. Es el mismo para todos los pedidos de
     * ese teléfono, así que se puede mandar antes de que la guía exista — y un
     * pedido sin armar quedaba marcado como preparado. La foto de la etiqueta
     * solo existe si el paquete está armado, pesado y etiquetado: esa sí es
     * prueba de que salió.
     */
    public static function alGuardarMensaje(?WaConversacion $conv, ?string $texto): void
    {
        if (! $conv) return;

        try {
            if (static::pareceOrden($texto)) {
                static::marcarPedido($conv);
            }
        } catch (\Throwable $e) {
            // Etiquetar es una comodidad. Que falle no puede costar un mensaje
            // ni tumbar el webhook.
            Log::warning('Etiquetado automático: ' . $e->getMessage());
        }
    }

    /**
     * Hay una orden nueva en esta conversación.
     *
     * Una orden nueva EMPIEZA EL RECORRIDO DE CERO. Eso significa soltar las
     * etiquetas del pedido anterior y olvidar la guía vieja que se estaba
     * vigilando.
     *
     * Sin esto pasaba algo grave: un cliente que ya había recibido su pedido
     * volvía a pedir, la conversación quedaba marcada "Pedidos" Y "Entregados"
     * a la vez, y en la siguiente revisión el vigilante miraba la guía VIEJA
     * —que seguía entregada— y le quitaba el "Pedidos". El pedido nuevo
     * desaparecía del tablero y nadie lo armaba.
     */
    public static function marcarPedido(WaConversacion $conv): void
    {
        $pedido = WaEtiqueta::porRol('pedido');
        if (! $pedido) return;

        // syncWithoutDetaching: no le toca las etiquetas que no son del
        // recorrido. Si además está marcada como "San Miguel", sigue estándolo.
        $conv->etiquetas()->syncWithoutDetaching([$pedido->id]);

        // Fuera las del recorrido anterior.
        foreach (['procesada', 'entregada'] as $rol) {
            $e = WaEtiqueta::porRol($rol);
            if ($e) $conv->etiquetas()->detach($e->id);
        }

        // Y a olvidar la guía anterior: si no, el vigilante seguiría
        // preguntando por un paquete que ya llegó hace una semana.
        try {
            $conv->forceFill([
                'guia'              => null,
                'etapa_envio'       => null,
                'entregas_seguidas' => 0,
                'revisado_at'       => null,
            ])->save();
        } catch (\Throwable $e) {
            // Si las columnas todavía no existen, no pasa nada: el etiquetado
            // es lo que importa.
        }
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

    /**
     * El courier confirmó la entrega.
     *
     * NO le toca el "Pedidos". Si esa etiqueta está puesta es porque llegó una
     * orden NUEVA después de este envío, y ese pedido todavía está por armarse.
     * Quitárselo acá lo borraba del tablero — que es lo que pasó.
     */
    public static function marcarEntregada(WaConversacion $conv): void
    {
        $fin = WaEtiqueta::porRol('entregada');
        if ($fin) $conv->etiquetas()->syncWithoutDetaching([$fin->id]);

        $lista = WaEtiqueta::porRol('procesada');
        if ($lista) $conv->etiquetas()->detach($lista->id);
    }

    /**
     * Se había marcado entregada y el courier se desdijo.
     *
     * Pasa: al repartidor se le va marcar entregado y lo corrige después. La
     * etiqueta tiene que poder volver, no solo avanzar — si solo avanzara, un
     * error de ellos se quedaría acá para siempre.
     */
    public static function volverAPreparada(WaConversacion $conv): void
    {
        $lista = WaEtiqueta::porRol('procesada');
        if ($lista) $conv->etiquetas()->syncWithoutDetaching([$lista->id]);

        $fin = WaEtiqueta::porRol('entregada');
        if ($fin) $conv->etiquetas()->detach($fin->id);
    }
}
