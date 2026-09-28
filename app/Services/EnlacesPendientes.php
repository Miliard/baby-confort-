<?php

namespace App\Services;

use App\Models\GuiaBorrador;
use App\Models\WaConversacion;
use Illuminate\Support\Facades\Log;

/**
 * El enlace de rastreo que no pudo salir porque la ventana estaba cerrada.
 *
 * WhatsApp solo deja escribirle a un cliente dentro de las 24 horas desde su
 * último mensaje. Si la guía se arma después —que es lo normal: el pedido se
 * cerró ayer y la guía se hace hoy—, el enlace de rastreo no puede salir en
 * el momento.
 *
 * Antes quedaba escrito en el cuadro, esperando que alguien se acordara de
 * mandarlo cuando el cliente escribiera. Y como no salía, la conversación
 * tampoco pasaba a Preparados.
 *
 * Ahora: la guía queda marcada como "enlace sin mandar" (enviado_at vacío),
 * y en cuanto el cliente escribe cualquier cosa —y con eso se abre la
 * ventana—, el enlace sale solo. Una sola vez.
 */
class EnlacesPendientes
{
    /**
     * Cuántos días se sigue esperando para mandar un enlace atrasado.
     *
     * Pasado eso el paquete probablemente ya llegó, y mandarle a alguien el
     * rastreo de algo que ya tiene en la mano confunde más de lo que ayuda.
     */
    private const DIAS = 3;

    /**
     * Si esta conversación tiene un enlace esperando, lo manda.
     *
     * Se llama cuando el cliente escribe. Manda solo el de la guía más nueva:
     * si hubiera dos pendientes, el enlace es por teléfono y sirve para los
     * dos pedidos, así que mandarlo dos veces sería repetir lo mismo.
     *
     * @return bool si mandó algo
     */
    public static function mandar(WaConversacion $conv): bool
    {
        if (! $conv->ventanaAbierta()) return false;

        $pendientes = static::de($conv);
        if (! $pendientes) return false;

        $guia = $pendientes[0];

        try {
            $m = WhatsappApi::enviarTexto(
                $conv,
                GuiaBorrador::mensajeCliente($guia->toArray()),
                null,     // sin usuario: lo mandó el sistema
                true      // automático: se dibuja distinto en el chat
            );

            if ($m->estado === 'fallido') {
                // El mensaje se anota antes de salir, y al anotarse ya las da
                // por mandadas (ver Etiquetado). Si falló, se deshace: tienen
                // que seguir pendientes para el próximo intento.
                foreach ($pendientes as $g) {
                    $g->forceFill(['enviado_at' => null])->save();
                }

                return false;
            }

            // Todas las pendientes de este teléfono quedan como mandadas: el
            // enlace que salió les sirve a todas.
            foreach ($pendientes as $g) {
                $g->forceFill(['enviado_at' => now()])->save();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Enlace de rastreo atrasado: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Las guías de esta conversación con el enlace sin mandar, la más nueva
     * primero.
     */
    public static function de(WaConversacion $conv): array
    {
        $tel = WaConversacion::telefonoCorto($conv->telefono);
        if (strlen($tel) !== 8) return [];

        try {
            return GuiaBorrador::whereNull('enviado_at')
                ->where('created_at', '>=', now()->subDays(static::DIAS))
                ->orderByDesc('id')
                ->get()
                ->filter(fn ($g) => WaConversacion::telefonoCorto($g->telefono) === $tel)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Marcar como mandado sin mandar: ya salió por otro lado.
     *
     * Pasa cuando el enlace se manda a mano, desde el cuadro o desde el
     * teléfono. Sin esto, cuando el cliente escribiera, el sistema lo
     * mandaría otra vez.
     */
    public static function darPorMandados(WaConversacion $conv): void
    {
        foreach (static::de($conv) as $g) {
            try {
                $g->forceFill(['enviado_at' => now()])->save();
            } catch (\Throwable $e) {
            }
        }
    }
}
